/**
 * Multizone admin — browser-style multi-tab workspace
 */
;(function (window, document, $) {
  'use strict'

  var STORAGE_KEY = 'mz.admin.tabs.v1'
  var MAX_TABS = 20

  var state = {
    tabs: [],
    activeId: null,
    mru: []
  }

  var els = {
    list: null,
    panes: null,
    scrollLeft: null,
    scrollRight: null
  }

  var cfg = {
    adminRoot: '/admin',
    dashboardUrl: 'dashboard.php',
    dashboardTitle: 'Dashboard',
    initialOpen: ''
  }

  function extendCfg () {
    var t = window.MZ_TABS || {}
    if (t.adminRoot) cfg.adminRoot = String(t.adminRoot).replace(/\/$/, '') || '/admin'
    if (t.dashboardUrl) cfg.dashboardUrl = t.dashboardUrl
    if (t.dashboardTitle) cfg.dashboardTitle = t.dashboardTitle
    if (typeof t.initialOpen === 'string') cfg.initialOpen = t.initialOpen
  }

  function normalizePath (href) {
    if (!href || href === '#' || href.indexOf('javascript:') === 0) return null

    var a = document.createElement('a')
    a.href = href
    var path = a.pathname || ''
    var search = a.search || ''
    var hash = a.hash || ''

    // Ignore external / public site / logout / login
    if (a.target === '_blank') return null

    var root = cfg.adminRoot
    if (path.indexOf(root) !== 0 && path.indexOf('/admin') === -1) {
      // Relative resolution may already be under admin via <base>
      // Keep if it looks like an admin php page
    }

    // Strip admin root to get relative key
    var rel = path
    if (root && rel.indexOf(root) === 0) {
      rel = rel.slice(root.length)
    }
    rel = rel.replace(/^\/+/, '')

    // Non-admin destinations
    if (!rel || /\.\.\//.test(rel)) {
      // ../index.php (view website) — leave alone
      if (/index\.php$/i.test(path) && path.indexOf(root) !== 0) return null
    }

    if (!/\.php$/i.test(rel.split('?')[0])) {
      // Allow paths that ended up absolute
      if (!/\.php$/i.test(path)) return null
      rel = path.replace(/^\/+/, '')
      if (root && ('/' + rel).indexOf(root) === 0) {
        rel = ('/' + rel).slice(root.length).replace(/^\/+/, '')
      }
    }

    // Exclude auth / shell / ajax / assets
    var file = rel.split('?')[0].split('/').pop().toLowerCase()
    var skipFiles = {
      'index.php': 1,
      'logout.php': 1,
      'app.php': 1,
      'connection.php': 1,
      'bootstrap.php': 1
    }
    if (skipFiles[file]) return null
    if (/\/ajax\//i.test('/' + rel)) return null
    if (/^ajax\//i.test(rel)) return null

    // Drop mz_embed from identity key (same page with/without embed = same tab)
    var cleanSearch = search
    if (cleanSearch) {
      var params = cleanSearch.replace(/^\?/, '').split('&').filter(function (p) {
        return p && p.indexOf('mz_embed=') !== 0
      })
      cleanSearch = params.length ? ('?' + params.join('&')) : ''
    }

    return rel + cleanSearch + hash
  }

  function tabIdFromPath (path) {
    // Identity without hash for duplicate prevention of same page+query
    return path.split('#')[0]
  }

  function titleFromLink ($link, path) {
    var text = ''
    var $p = $link.children('p').first()
    if ($p.length) {
      text = $p.clone().children().remove().end().text()
    }
    if (!text) text = $link.text()
    text = String(text || '').replace(/\s+/g, ' ').trim()
    if (text) return text

    var file = (path || '').split('?')[0].split('/').pop() || 'Page'
    return file.replace(/\.php$/i, '').replace(/[-_]/g, ' ').replace(/\b\w/g, function (c) {
      return c.toUpperCase()
    })
  }

  function toAbsoluteUrl (path) {
    var clean = String(path || '').replace(/^\/+/, '')
    return cfg.adminRoot + '/' + clean
  }

  /** Frame URL relative to app.php (admin root) so query params are never dropped. */
  function toFrameSrc (path) {
    var clean = String(path || '').replace(/^\/+/, '')
    var qIndex = clean.indexOf('?')
    var file = qIndex === -1 ? clean : clean.slice(0, qIndex)
    var search = qIndex === -1 ? '' : clean.slice(qIndex + 1)
    var params = search ? search.split('&').filter(Boolean) : []
    params = params.filter(function (p) { return p.indexOf('mz_embed=') !== 0 })
    params.push('mz_embed=1')
    return file + '?' + params.join('&')
  }

  function withEmbed (url) {
    return toFrameSrc(url.replace(/^https?:\/\/[^/]+/i, '').replace(cfg.adminRoot + '/', ''))
  }

  function persist () {
    try {
      var payload = {
        tabs: state.tabs.map(function (t) {
          return { id: t.id, path: t.path, title: t.title, pinned: !!t.pinned }
        }),
        activeId: state.activeId,
        mru: state.mru.slice(0, MAX_TABS)
      }
      sessionStorage.setItem(STORAGE_KEY, JSON.stringify(payload))
    } catch (e) {}
  }

  function restore () {
    try {
      var raw = sessionStorage.getItem(STORAGE_KEY)
      if (!raw) return null
      return JSON.parse(raw)
    } catch (e) {
      return null
    }
  }

  function touchMru (id) {
    state.mru = state.mru.filter(function (x) { return x !== id })
    state.mru.unshift(id)
  }

  function findTab (id) {
    for (var i = 0; i < state.tabs.length; i++) {
      if (state.tabs[i].id === id) return state.tabs[i]
    }
    return null
  }

  function renderTabButton (tab) {
    var btn = document.createElement('div')
    btn.className = 'mz-tab' + (tab.id === state.activeId ? ' is-active' : '') + (tab.pinned ? ' is-pinned' : '')
    btn.setAttribute('role', 'tab')
    btn.setAttribute('data-tab-id', tab.id)
    btn.setAttribute('aria-selected', tab.id === state.activeId ? 'true' : 'false')
    btn.title = tab.title

    var title = document.createElement('span')
    title.className = 'mz-tab-title'
    title.textContent = tab.title
    btn.appendChild(title)

    if (!tab.pinned) {
      var close = document.createElement('button')
      close.type = 'button'
      close.className = 'mz-tab-close'
      close.setAttribute('aria-label', 'Close ' + tab.title)
      close.innerHTML = '&times;'
      close.addEventListener('click', function (e) {
        e.preventDefault()
        e.stopPropagation()
        closeTab(tab.id)
      })
      btn.appendChild(close)
    }

    btn.addEventListener('click', function (e) {
      if ($(e.target).closest('.mz-tab-close').length) return
      activateTab(tab.id, { pushHistory: true })
    })

    btn.addEventListener('auxclick', function (e) {
      // Middle-click close
      if (e.button === 1 && !tab.pinned) {
        e.preventDefault()
        closeTab(tab.id)
      }
    })

    return btn
  }

  function ensurePane (tab) {
    var existing = document.querySelector('.mz-tab-pane[data-tab-id="' + cssEscape(tab.id) + '"]')
    if (existing) return existing

    var pane = document.createElement('div')
    pane.className = 'mz-tab-pane'
    pane.setAttribute('data-tab-id', tab.id)

    var iframe = document.createElement('iframe')
    iframe.className = 'mz-tab-frame'
    iframe.title = tab.title
    iframe.setAttribute('data-tab-id', tab.id)
    iframe.src = toFrameSrc(tab.path)
    iframe.addEventListener('load', function () {
      syncTabTitleFromFrame(tab, iframe)
    })

    pane.appendChild(iframe)
    els.panes.appendChild(pane)
    return pane
  }

  function syncTabTitleFromFrame (tab, iframe) {
    if (tab.pinned) return
    try {
      var doc = iframe.contentDocument || (iframe.contentWindow && iframe.contentWindow.document)
      if (!doc || !doc.title) return
      var pageTitle = String(doc.title).split('|')[0].split('—')[0].split(' - ')[0].trim()
      if (!pageTitle || pageTitle === tab.title) return
      // Ignore overly long or generic titles
      if (pageTitle.length > 48) pageTitle = pageTitle.slice(0, 45) + '…'
      tab.title = pageTitle
      var btn = els.list && els.list.querySelector('.mz-tab[data-tab-id="' + cssEscape(tab.id) + '"] .mz-tab-title')
      if (btn) btn.textContent = pageTitle
      if (state.activeId === tab.id) {
        document.title = pageTitle + ' — Multizone Travels'
      }
      persist()
    } catch (e) {}
  }

  function cssEscape (value) {
    if (window.CSS && CSS.escape) return CSS.escape(value)
    return String(value).replace(/"/g, '\\"')
  }

  function renderAll () {
    if (!els.list || !els.panes) return
    els.list.innerHTML = ''
    state.tabs.forEach(function (tab) {
      els.list.appendChild(renderTabButton(tab))
      ensurePane(tab)
    })

    // Remove panes for closed tabs
    Array.prototype.slice.call(els.panes.querySelectorAll('.mz-tab-pane')).forEach(function (pane) {
      var id = pane.getAttribute('data-tab-id')
      if (!findTab(id)) pane.parentNode.removeChild(pane)
    })

    Array.prototype.slice.call(els.panes.querySelectorAll('.mz-tab-pane')).forEach(function (pane) {
      var id = pane.getAttribute('data-tab-id')
      if (id === state.activeId) {
        pane.classList.add('is-active')
      } else {
        pane.classList.remove('is-active')
      }
    })

    updateSidebarActive()
    updateScrollButtons()
    persist()
  }

  function updateScrollButtons () {
    if (!els.list || !els.scrollLeft || !els.scrollRight) return
    var el = els.list
    var overflow = el.scrollWidth > el.clientWidth + 2
    els.scrollLeft.hidden = !overflow
    els.scrollRight.hidden = !overflow
  }

  function scrollTabs (dir) {
    if (!els.list) return
    els.list.scrollBy({ left: dir * 160, behavior: 'smooth' })
  }

  function updateSidebarActive () {
    var active = findTab(state.activeId)
    var activePath = active ? active.path.split('?')[0] : ''

    $('.nav-sidebar .nav-link').each(function () {
      var $link = $(this)
      var $li = $link.parent('li')
      if ($li.hasClass('has-treeview')) return

      var href = $link.attr('href')
      var path = normalizePath(href)
      if (!path) return

      var linkPath = path.split('?')[0]
      var isActive = activePath && (linkPath === activePath || activePath.indexOf(linkPath) === 0)
      $link.toggleClass('active', !!isActive)
    })

    // Keep tree parents open for active leaf
    $('.nav-sidebar .nav-link.active').each(function () {
      $(this).parents('.nav-item.has-treeview').addClass('menu-open')
    })
  }

  function openTab (path, title, options) {
    options = options || {}
    var norm = normalizePath(path) || normalizePath(toAbsoluteUrl(path))
    if (!norm) return null

    var id = tabIdFromPath(norm)
    var existing = findTab(id)
    if (existing) {
      if (title && !existing.pinned) existing.title = title
      activateTab(id, { pushHistory: options.pushHistory !== false })
      return existing
    }

    if (state.tabs.length >= MAX_TABS) {
      // Close oldest non-pinned non-active
      for (var i = state.mru.length - 1; i >= 0; i--) {
        var cand = findTab(state.mru[i])
        if (cand && !cand.pinned && cand.id !== state.activeId) {
          closeTab(cand.id, { skipActivate: true })
          break
        }
      }
    }

    var tab = {
      id: id,
      path: norm,
      title: title || titleFromPath(norm),
      pinned: !!options.pinned
    }
    state.tabs.push(tab)
    activateTab(id, { pushHistory: options.pushHistory !== false, skipRender: false })
    return tab
  }

  function titleFromPath (path) {
    var file = (path || '').split('?')[0].split('/').pop() || 'Page'
    return file.replace(/\.php$/i, '').replace(/[-_]/g, ' ').replace(/\b\w/g, function (c) {
      return c.toUpperCase()
    })
  }

  function activateTab (id, options) {
    options = options || {}
    var tab = findTab(id)
    if (!tab) return

    state.activeId = id
    touchMru(id)
    ensurePane(tab)
    renderAll()

    // Scroll active tab into view
    var btn = els.list && els.list.querySelector('.mz-tab[data-tab-id="' + cssEscape(id) + '"]')
    if (btn && btn.scrollIntoView) {
      btn.scrollIntoView({ inline: 'nearest', block: 'nearest', behavior: 'smooth' })
    }

    if (options.pushHistory !== false) {
      pushHistory(tab)
    }

    document.title = tab.title + ' — Multizone Travels'
  }

  function closeTab (id, options) {
    options = options || {}
    var tab = findTab(id)
    if (!tab || tab.pinned) return

    var idx = state.tabs.indexOf(tab)
    state.tabs.splice(idx, 1)
    state.mru = state.mru.filter(function (x) { return x !== id })

    var pane = els.panes && els.panes.querySelector('.mz-tab-pane[data-tab-id="' + cssEscape(id) + '"]')
    if (pane && pane.parentNode) pane.parentNode.removeChild(pane)

    if (state.activeId === id && !options.skipActivate) {
      var nextId = state.mru[0] || (state.tabs[0] && state.tabs[0].id)
      if (nextId) {
        activateTab(nextId, { pushHistory: true })
        return
      }
    }

    renderAll()
  }

  function pushHistory (tab) {
    try {
      var url = cfg.adminRoot + '/app.php'
      if (tab && tab.path && tab.path.split('?')[0] !== cfg.dashboardUrl) {
        url += '?open=' + encodeURIComponent(tab.path)
      }
      window.history.pushState({ mzTabId: tab.id }, tab.title, url)
    } catch (e) {}
  }

  function replaceHistory (tab) {
    try {
      var url = cfg.adminRoot + '/app.php'
      if (tab && tab.path && tab.path.split('?')[0] !== cfg.dashboardUrl) {
        url += '?open=' + encodeURIComponent(tab.path)
      }
      window.history.replaceState({ mzTabId: tab.id }, tab.title, url)
    } catch (e) {}
  }

  function isTabbableAdminLink (el) {
    var $link = $(el)
    if (!$link.length) return false
    if ($link.attr('target') === '_blank') return false
    if ($link.data('mzNoTab')) return false
    if ($link.closest('.main-sidebar, .mz-topbar, .mz-tab-bar').length === 0 &&
        !$link.hasClass('mz-topbar-brand') &&
        !$link.hasClass('mz-topbar-link')) {
      // Only intercept shell chrome links, not random content (shell has no content links)
    }

    var href = $link.attr('href')
    if (!href || href === '#') return false

    var $li = $link.parent('li')
    if ($li.hasClass('has-treeview')) return false

    return !!normalizePath(href)
  }

  function onShellLinkClick (e) {
    var $link = $(e.currentTarget)
    if ($link.attr('target') === '_blank') return
    if ($link.data('mzNoTab')) return

    var href = $link.attr('href')
    if (!href || href === '#') return

    // Treeview parents
    if ($link.parent('li').hasClass('has-treeview')) return

    var path = normalizePath(href)
    if (!path) return

    e.preventDefault()
    e.stopPropagation()

    var title = titleFromLink($link, path)
    openTab(path, title, { pushHistory: true })
  }

  function bindShellNavigation () {
    // Sidebar leaf links
    $(document).on('click.mzTabs', '.nav-sidebar .nav-link', function (e) {
      var $li = $(this).parent('li')
      if ($li.hasClass('has-treeview')) return
      onShellLinkClick(e)
    })

    // Brand / home / profile / settings in topbar (same-window admin pages)
    $(document).on('click.mzTabs', '.mz-topbar-brand, .mz-topbar-link, .mz-brand-link, .main-header .dropdown-item', function (e) {
      var href = $(this).attr('href')
      if (!href || href === '#' || $(this).attr('target') === '_blank') return
      if (/logout\.php/i.test(href)) return
      if (!normalizePath(href)) return
      onShellLinkClick(e)
    })
  }

  function bootstrapTabs () {
    var saved = restore()
    var openParam = cfg.initialOpen ? normalizePath(toAbsoluteUrl(cfg.initialOpen)) || normalizePath(cfg.initialOpen) : ''

    // Always ensure Dashboard pinned first
    openTab(cfg.dashboardUrl, cfg.dashboardTitle, { pinned: true, pushHistory: false })

    if (saved && Array.isArray(saved.tabs)) {
      saved.tabs.forEach(function (t) {
        if (!t || !t.path) return
        var id = tabIdFromPath(t.path)
        if (findTab(id)) {
          var ex = findTab(id)
          if (t.title) ex.title = t.title
          return
        }
        if (t.pinned) return // dashboard already added
        openTab(t.path, t.title || titleFromPath(t.path), { pushHistory: false })
      })
      if (saved.mru && Array.isArray(saved.mru)) {
        state.mru = saved.mru.filter(function (id) { return !!findTab(id) })
      }
    }

    var activate = openParam || (saved && saved.activeId) || tabIdFromPath(cfg.dashboardUrl)
    if (openParam) {
      var openTitle = titleFromPath(openParam)
      openTab(openParam, openTitle, { pushHistory: false })
      activate = tabIdFromPath(openParam)
    }

    if (findTab(activate)) {
      activateTab(activate, { pushHistory: false })
      replaceHistory(findTab(activate))
    } else {
      activateTab(tabIdFromPath(cfg.dashboardUrl), { pushHistory: false })
      replaceHistory(findTab(state.activeId))
    }
  }

  function onPopState (e) {
    var id = e.state && e.state.mzTabId
    if (id && findTab(id)) {
      activateTab(id, { pushHistory: false })
      return
    }
    // Fallback: read ?open=
    var params = new URLSearchParams(window.location.search)
    var open = params.get('open')
    if (open) {
      var path = normalizePath(toAbsoluteUrl(open)) || open
      openTab(path, titleFromPath(path), { pushHistory: false })
    }
  }

  function initShell () {
    extendCfg()
    els.list = document.getElementById('mzTabList')
    els.panes = document.getElementById('mzTabPanes')
    els.scrollLeft = document.getElementById('mzTabScrollLeft')
    els.scrollRight = document.getElementById('mzTabScrollRight')

    if (!els.list || !els.panes) return

    if (els.scrollLeft) {
      els.scrollLeft.addEventListener('click', function () { scrollTabs(-1) })
    }
    if (els.scrollRight) {
      els.scrollRight.addEventListener('click', function () { scrollTabs(1) })
    }
    if (els.list) {
      els.list.addEventListener('scroll', updateScrollButtons)
      window.addEventListener('resize', updateScrollButtons)
    }

    bindShellNavigation()
    window.addEventListener('popstate', onPopState)
    bootstrapTabs()

    // Messages from embed iframes (optional future: open tab requests)
    window.addEventListener('message', function (ev) {
      var data = ev.data
      if (!data || typeof data !== 'object') return

      if (data.type === 'mz-tabs-open') {
        if (data.path) openTab(data.path, data.title || titleFromPath(data.path), { pushHistory: true })
        return
      }

      // Relay data-change notifications to every open tab pane (e.g. new CRM user).
      if (data.type === 'mz-data-changed' && els.panes) {
        Array.prototype.slice.call(els.panes.querySelectorAll('iframe.mz-tab-frame')).forEach(function (frame) {
          try {
            if (frame.contentWindow && frame.contentWindow !== ev.source) {
              frame.contentWindow.postMessage(data, '*')
            }
          } catch (e) {}
        })
      }
    })
  }

  /**
   * Top-level admin pages (not shell, not embed) → bounce into the tab workspace.
   */
  function maybeRedirectIntoShell () {
    if (window.top !== window.self) return
    if (document.documentElement.classList.contains('mz-embed')) return
    if (document.body && document.body.classList.contains('mz-tab-shell')) return

    var path = window.location.pathname || ''
    var root = (window.MZ_ADMIN && window.MZ_ADMIN.adminRoot) || cfg.adminRoot
    // Detect app.php / login / logout
    if (/\/app\.php$/i.test(path)) return
    if (/\/index\.php$/i.test(path)) return
    if (/\/logout\.php$/i.test(path)) return
    if (/\/ajax\//i.test(path)) return

    // Only redirect pages under admin root that look like app pages
    var adminRoot = String(root || '/admin').replace(/\/$/, '')
    if (path.indexOf(adminRoot) !== 0) return

    var rel = path.slice(adminRoot.length).replace(/^\/+/, '')
    if (!rel || !/\.php$/i.test(rel.split('?')[0])) return

    var open = rel + (window.location.search || '') + (window.location.hash || '')
    // Strip mz_embed if present
    open = open.replace(/([?&])mz_embed=1&?/, '$1').replace(/[?&]$/, '')

    var target = adminRoot + '/app.php?open=' + encodeURIComponent(open)
    window.location.replace(target)
  }

  // Public API
  window.MZTabWorkspace = {
    open: openTab,
    close: closeTab,
    activate: activateTab,
    normalizePath: normalizePath
  }

  $(function () {
    extendCfg()
    if (document.body && document.body.classList.contains('mz-tab-shell')) {
      initShell()
      return
    }
    // Non-shell: optionally redirect into workspace (set by header-links gate)
    if (window.MZ_TABS && window.MZ_TABS.redirectToShell) {
      maybeRedirectIntoShell()
    }
  })
})(window, document, jQuery)
