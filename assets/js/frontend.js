(function(){
  function getInt(v, d){ var n=parseInt(v,10); return isNaN(n)?d:n; }

  // Store best-known totals per query id (or "default")
  var totals = Object.create(null);

  function keyFor(queryId){ return queryId ? String(queryId) : 'default'; }

  // Walk up DOM to find a useful scope (for pages with multiple listings)
  function findScope(queryId, fromEl){
    // If we have an element (event target), try nearest [data-query-id]
    if(fromEl && fromEl.closest){
      var near = fromEl.closest('[data-query-id]');
      if(near) return near;
    }

    // Try direct selector by query id
    if(queryId){
      // JetEngine / JetSmartFilters can place data-query-id on different wrappers
      var scope = document.querySelector('[data-query-id="'+queryId+'"]');
      if(scope) return scope;
    }

    // Fallback: whole document
    return document;
  }

  function findListing(scope, queryId){
    if(!scope) scope = document;

    // Prefer listing inside the scope
    var grid = scope.querySelector('.jet-listing-grid');
    if(grid) return grid;

    // If scope is document but queryId is known, try stricter selectors
    if(queryId){
      grid = document.querySelector('[data-query-id="'+queryId+'"] .jet-listing-grid')
         || document.querySelector('.jet-listing-grid[data-query-id="'+queryId+'"]');
      if(grid) return grid;
    }

    // Last resort: first listing on page
    return document.querySelector('.jet-listing-grid');
  }

  function countItems(grid){
    if(!grid) return 0;

    // JetEngine listing items are usually .jet-listing-grid__item
    var items = grid.querySelectorAll('.jet-listing-grid__item');
    if(items && items.length) return items.length;

    // Fallbacks (some skins / templates)
    items = grid.querySelectorAll('.jet-listing-grid__items > *');
    return items ? items.length : 0;
  }

  function currentPage(scope, grid){
    var root = scope || (grid ? (grid.closest('[data-query-id]') || grid.parentElement) : document);
    if(!root || !root.querySelector) return 1;

    var selectors = [
      '.page-numbers.current',
      '.page-numbers[aria-current="page"]',
      '.jet-filters-pagination__current',
      '.jet-filters-pagination__item--active',
      '.jet-smart-filters-pagination__current',
      '.jet-smart-filters-pagination__item--active'
    ];

    for(var i=0;i<selectors.length;i++){
      var cur = root.querySelector(selectors[i]);
      if(cur){
        var n = parseInt((cur.textContent||'').trim(),10);
        if(!isNaN(n)) return n;
      }
    }
    return 1;
  }

  function findTotalFromJetCount(scope, queryId){
    // JetEngine "Query Results Count" widget often updates after AJAX filtering.
    // Common markup:
    // <div class="jet-engine-query-count count-type-total" data-query="X">123</div>
    var sel = '.jet-engine-query-count.count-type-total';
    var el = null;

    if(queryId){
      el = (scope && scope.querySelector) ? scope.querySelector(sel + '[data-query="'+queryId+'"]') : null;
      if(!el) el = document.querySelector(sel + '[data-query="'+queryId+'"]');
    }
    if(!el){
      el = (scope && scope.querySelector) ? scope.querySelector(sel) : null;
      if(!el) el = document.querySelector(sel);
    }
    if(!el) return null;

    var t = (el.textContent||'').trim();
    var n = parseInt(t,10);
    return isNaN(n) ? null : n;
  }

  function deepFindNumber(obj, keys, depth){
    if(!obj || depth <= 0) return null;

    // Direct key hits
    for(var i=0;i<keys.length;i++){
      if(Object.prototype.hasOwnProperty.call(obj, keys[i])){
        var n = getInt(obj[keys[i]], null);
        if(n !== null) return n;
      }
    }

    // Recurse into objects/arrays
    if(Array.isArray(obj)){
      for(i=0;i<obj.length;i++){
        var a = obj[i];
        if(a && typeof a === 'object'){
          var an = deepFindNumber(a, keys, depth-1);
          if(an !== null) return an;
        }
      }
      return null;
    }

    if(typeof obj === 'object'){
      for(var k in obj){
        if(!Object.prototype.hasOwnProperty.call(obj, k)) continue;
        var v = obj[k];
        if(v && typeof v === 'object'){
          var nn = deepFindNumber(v, keys, depth-1);
          if(nn !== null) return nn;
        }
      }
    }

    return null;
  }

  function extractTotalAndQueryIdFromEvent(e){
    var out = { total: null, queryId: null };

    try{
      var d = e && e.detail ? e.detail : null;
      if(d){
        // Common query id keys
        out.queryId = d.query_id || d.queryId || (d.query && d.query.query_id) || null;

        // Try to find total/found posts anywhere in detail/response
        var keys = ['found_posts','foundPosts','total','total_posts','totalPosts','postsFound','posts_found'];
        out.total = deepFindNumber(d, keys, 6);

        // If response is a string, regex it
        if(out.total === null && d.response && typeof d.response === 'string'){
          var m = d.response.match(/found_posts\"?\s*:\s*(\d+)/i) || d.response.match(/total\"?\s*:\s*(\d+)/i);
          if(m) out.total = getInt(m[1], null);
        }

        // If response is an object, stringify lightly then regex
        if(out.total === null && d.response && typeof d.response === 'object'){
          var s = '';
          try{ s = JSON.stringify(d.response); }catch(err){}
          if(s){
            var mm = s.match(/found_posts\"?\s*:\s*(\d+)/i) || s.match(/total\"?\s*:\s*(\d+)/i);
            if(mm) out.total = getInt(mm[1], null);
          }
        }
      }
    }catch(err){}

    return out;
  }

  function tpl(str, map){
    return (str||'').replace(/\{(\w+)\}/g, function(_,k){
      return map[k] !== undefined && map[k] !== null ? String(map[k]) : '';
    });
  }

  function updateEl(el){
    if(!el) return;
    if(el.getAttribute('data-enable_ajax') !== 'yes') return;

    var perPage = getInt(el.getAttribute('data-per_page'), 10);
    var queryId = el.getAttribute('data-query_id') || '';
    var fallback = el.getAttribute('data-ajax_fallback') || 'keep';
    var ajaxTpl = el.getAttribute('data-ajax_template') || 'Toont {start}-{end} {results_label}';
    var fullTpl = el.getAttribute('data-label_template') || 'Toont {start}-{end} van {total} {results_label}';
    var resultsLabel = el.getAttribute('data-results_label') || '';
    var totalInitial = getInt(el.getAttribute('data-total_initial'), null);

    var scope = findScope(queryId);
    var grid = findListing(scope, queryId);
    var visible = countItems(grid);
    var page = currentPage(scope, grid);

    // JetSmartFilters sometimes updates the listing markup but leaves `found_posts` in data-nav
    // untouched (e.g. still the original total). In those cases Jet often sets data-pages="1".
    // If we detect this inconsistency, prefer the visible count as the most reliable total.
    var pages = null;
    try {
      if(grid){
        // data-pages is usually on .jet-listing-grid__items (your grid wrapper)
        pages = getInt(grid.getAttribute('data-pages'), null);
        if(pages === null){
          // sometimes the attribute is on a child wrapper
          var itemsWrap = grid.querySelector('.jet-listing-grid__items');
          if(itemsWrap) pages = getInt(itemsWrap.getAttribute('data-pages'), null);
        }
      }
    } catch(err){}

    var start = visible > 0 ? ((page-1)*perPage + 1) : 0;
    var end = visible > 0 ? ((page-1)*perPage + visible) : 0;

    // Best-effort total:
    // 1) Jet query count widget (if present)
    // 2) stored total from last AJAX event (per queryId)
    // 3) initial total from server
    var totalJet = findTotalFromJetCount(scope, queryId);
    var totalStored = totals[keyFor(queryId)];
    var total = (totalJet !== null) ? totalJet : (totalStored !== undefined ? totalStored : totalInitial);

    // Heuristic override (only when it clearly indicates a stale total):
    // - only one page reported after AJAX
    // - fewer visible items than perPage (so it's not just a full page)
    // - total is larger than visible
    // In that scenario, use visible as total.
    if(pages === 1 && visible > 0 && visible < perPage && total !== null && total !== undefined && total > visible){
      total = visible;
    }

    // If nothing visible, and hide_when_empty was enabled server-side, the element might not exist.
    // If it exists, keep behavior consistent.
    if(fallback === 'omit_total'){
      el.textContent = tpl(ajaxTpl, {
        start: start,
        end: end,
        visible: visible,
        results_label: resultsLabel
      }).replace(/\s+/g,' ').trim();
      return;
    }

    if(fallback === 'visible'){
      total = visible;
    }

    if(total === null || total === undefined){
      el.textContent = tpl(ajaxTpl, {
        start: start,
        end: end,
        visible: visible,
        results_label: resultsLabel
      }).replace(/\s+/g,' ').trim();
      return;
    }

    el.textContent = tpl(fullTpl, {
      start: start,
      end: end,
      total: total,
      visible: visible,
      results_label: resultsLabel
    }).replace(/\s+/g,' ').trim();
  }

  function updateAll(){
    var els = document.querySelectorAll('.rrw-results-range[data-rrw="1"]');
    for(var i=0;i<els.length;i++) updateEl(els[i]);
  }

  function onJetEvent(e){
    var extracted = extractTotalAndQueryIdFromEvent(e);
    var t = extracted.total;
    var qFromEvent = extracted.queryId;

    if(t !== null){
      if(qFromEvent){
        totals[keyFor(qFromEvent)] = t;
      } else {
        // Try to infer queryId from the event target DOM (best-effort)
        var scope = findScope(null, e && e.target ? e.target : null);
        var qFromDom = scope && scope.getAttribute ? scope.getAttribute('data-query-id') : null;
        if(qFromDom){
          totals[keyFor(qFromDom)] = t;
        } else {
          // Fallback: store for all widgets on page
          var els = document.querySelectorAll('.rrw-results-range[data-rrw="1"]');
          for(var i=0;i<els.length;i++){
            var qid = els[i].getAttribute('data-query_id') || '';
            totals[keyFor(qid)] = t;
          }
        }
      }
    }

    // Let Jet update the DOM first, then recalc
    setTimeout(updateAll, 0);
  }

  document.addEventListener('DOMContentLoaded', function(){ setTimeout(updateAll, 0); });

  // JetSmartFilters / JetEngine events (names differ per version)
  document.addEventListener('jet-smart-filters/ajax/loaded', onJetEvent, true);
  document.addEventListener('jet-smart-filters/after-filtering', onJetEvent, true);
  document.addEventListener('jet-engine/listing-grid/after-update', onJetEvent, true);

  // Extra: in some setups, filters only fire change events without the above hooks
  document.addEventListener('change', function(e){
    if(e.target && e.target.closest && e.target.closest('.jet-smart-filters')){
      setTimeout(updateAll, 150);
    }
  }, true);
})();