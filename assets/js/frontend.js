(function(){
  function getInt(v, d){ var n=parseInt(v,10); return isNaN(n)?d:n; }

  // Store best-known totals per query id (or "default")
  var totals = Object.create(null);

  function keyFor(queryId){ return queryId ? String(queryId) : 'default'; }

  function findScope(queryId){
    if(queryId){
      var scope = document.querySelector('[data-query-id="'+queryId+'"]');
      if(scope) return scope;
    }
    return document;
  }

  function findListing(scope){
    if(!scope) scope = document;
    return scope.querySelector('.jet-listing-grid') || document.querySelector('.jet-listing-grid');
  }

  function countItems(grid){
    if(!grid) return 0;
    var items = grid.querySelectorAll('.jet-listing-grid__item');
    return items ? items.length : 0;
  }

  function currentPage(scope, grid){
    var root = scope || (grid ? (grid.closest('[data-query-id]') || grid.parentElement) : document);
    var cur = root && root.querySelector ? root.querySelector('.page-numbers.current, .page-numbers[aria-current="page"]') : null;
    if(cur){
      var n = parseInt(cur.textContent.trim(),10);
      if(!isNaN(n)) return n;
    }
    return 1;
  }

  function findTotalFromJetCount(scope, queryId){
    // If a JetEngine "Query Results Count" widget exists (can be visually hidden),
    // it often updates its .count-type-total value after AJAX filtering.
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

  function extractTotalFromEvent(e){
    // JetSmartFilters / JetEngine sometimes provide the total in the event detail.
    // We'll try a few common shapes and a regex fallback.
    try{
      var d = e && e.detail ? e.detail : null;
      if(!d) return null;

      // Direct fields
      var candidates = [d.found_posts, d.foundPosts, d.total, d.total_posts, d.totalPosts];
      for(var i=0;i<candidates.length;i++){
        var n = getInt(candidates[i], null);
        if(n !== null) return n;
      }

      // Nested common fields
      if(d.response){
        var r = d.response;
        candidates = [r.found_posts, r.foundPosts, r.total, r.total_posts, r.totalPosts];
        for(i=0;i<candidates.length;i++){
          var nn = getInt(candidates[i], null);
          if(nn !== null) return nn;
        }
        // If response is a string, regex it
        if(typeof r === 'string'){
          var m = r.match(/found_posts\"?\s*:\s*(\d+)/i) || r.match(/total\"?\s*:\s*(\d+)/i);
          if(m) return getInt(m[1], null);
        } else {
          // If response is an object, stringify lightly
          var s = JSON.stringify(r);
          var mm = s.match(/found_posts\"?\s*:\s*(\d+)/i) || s.match(/total\"?\s*:\s*(\d+)/i);
          if(mm) return getInt(mm[1], null);
        }
      }
    }catch(err){}
    return null;
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
    var grid = findListing(scope);
    var visible = countItems(grid);
    var page = currentPage(scope, grid);

    var start = visible > 0 ? ((page-1)*perPage + 1) : 0;
    var end = visible > 0 ? ((page-1)*perPage + visible) : 0;

    // Best-effort total:
    // 1) Jet query count widget (if present)
    // 2) stored total from last AJAX event
    // 3) initial total from server
    var totalJet = findTotalFromJetCount(scope, queryId);
    var totalStored = totals[keyFor(queryId)];
    var total = (totalJet !== null) ? totalJet : (totalStored !== undefined ? totalStored : totalInitial);

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
    // Capture total if present
    var t = extractTotalFromEvent(e);
    if(t !== null){
      // Try to infer queryId from the active widgets on page (best effort)
      // If your widget has a query_id set, it will store per queryId.
      var els = document.querySelectorAll('.rrw-results-range[data-rrw="1"]');
      for(var i=0;i<els.length;i++){
        var qid = els[i].getAttribute('data-query_id') || '';
        totals[keyFor(qid)] = t;
      }
    }
    setTimeout(updateAll, 0);
  }

  document.addEventListener('DOMContentLoaded', function(){ setTimeout(updateAll, 0); });

  document.addEventListener('jet-smart-filters/ajax/loaded', onJetEvent, true);
  document.addEventListener('jet-smart-filters/after-filtering', onJetEvent, true);
  document.addEventListener('jet-engine/listing-grid/after-update', onJetEvent, true);

  document.addEventListener('change', function(e){
    if(e.target && e.target.closest && e.target.closest('.jet-smart-filters')){
      setTimeout(updateAll, 150);
    }
  }, true);
})();