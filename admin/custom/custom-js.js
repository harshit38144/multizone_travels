/**
 * Admin sidebar: leaf links navigate without collapsing parents; branch toggles open/close normally.
 * When the multi-tab shell is active, leaf navigation is handled by tabs.js instead.
 *
 * Also fixes mini-sidebar hover expand: AdminLTE adds .sidebar-focused on link focusin, which
 * keeps the collapsed sidebar expanded after a click until the user clicks elsewhere. Clear
 * that lock when the pointer leaves so hover-expand collapses again without an extra click.
 */
;(function ($) {
  'use strict'

  var SIDEBAR_SELECTOR = '.nav-sidebar[data-widget="treeview"]'
  var LINK_SELECTOR = '[data-widget="treeview"] .nav-link'
  var MAIN_SIDEBAR = '.main-sidebar'

  function isMiniSidebarCollapsed () {
    var $body = $('body')
    return $body.hasClass('sidebar-collapse') &&
      ($body.hasClass('sidebar-mini') || $body.hasClass('sidebar-mini-md'))
  }

  /** Drop focus-lock so :hover alone controls temporary expand while collapsed. */
  function clearSidebarFocusLock ($sidebar) {
    var $target = $sidebar && $sidebar.length ? $sidebar : $(MAIN_SIDEBAR)
    var $focused = $target.find('a:focus, button:focus, [tabindex]:focus')
    if ($focused.length) {
      $focused.blur()
    }
    $target.removeClass('sidebar-focused')
  }

  function bindMiniSidebarHoverCollapse () {
    $(document)
      .off('mouseleave.mzSidebarHover', MAIN_SIDEBAR)
      .on('mouseleave.mzSidebarHover', MAIN_SIDEBAR, function () {
        if (!isMiniSidebarCollapsed()) {
          return
        }
        clearSidebarFocusLock($(this))
      })

    // After leaf/submenu navigation in collapsed mini mode, release focus if the
    // pointer already left (SPA/tab clicks often keep the link focused).
    $(document)
      .off('click.mzSidebarHover', MAIN_SIDEBAR + ' .nav-link')
      .on('click.mzSidebarHover', MAIN_SIDEBAR + ' .nav-link', function () {
        if (!isMiniSidebarCollapsed()) {
          return
        }
        var $sidebar = $(this).closest(MAIN_SIDEBAR)
        window.setTimeout(function () {
          if (!$sidebar.length || !isMiniSidebarCollapsed()) {
            return
          }
          if ($sidebar.is(':hover')) {
            return
          }
          clearSidebarFocusLock($sidebar)
        }, 0)
      })
  }

  function patchTreeviewAccordion () {
    $(SIDEBAR_SELECTOR).each(function () {
      var inst = $(this).data('lte.treeview')
      if (inst && inst._config) {
        inst._config.accordion = false
        // Avoid slide animation residue that collapses nested open menus
        inst._config.animationSpeed = 0
      }
    })
  }

  /** Clear jQuery slideUp/slideDown inline styles so open trees keep natural height. */
  function fixOpenTreeviewLayout ($root) {
    var $scope = $root && $root.length ? $root : $(SIDEBAR_SELECTOR)
    $scope.find('.nav-item.menu-open > .nav-treeview').each(function () {
      this.style.removeProperty('height')
      this.style.removeProperty('overflow')
      this.style.removeProperty('padding-top')
      this.style.removeProperty('padding-bottom')
      this.style.removeProperty('margin-top')
      this.style.removeProperty('margin-bottom')
      this.style.display = 'block'
      this.style.height = 'auto'
      this.style.overflow = 'visible'
    })
  }

  /** On page load only — expand parents of the current page link; close others. */
  function openActiveMenuAncestors () {
    var $activeLeaves = $('.nav-sidebar .nav-link.active').filter(function () {
      return !$(this).parent('li').hasClass('has-treeview')
    })
    var $keepOpen = $()
    $activeLeaves.each(function () {
      $keepOpen = $keepOpen.add($(this).parents('.nav-item.has-treeview'))
    })

    $(SIDEBAR_SELECTOR).find('.nav-item.has-treeview').each(function () {
      var $li = $(this)
      var shouldOpen = $keepOpen.filter(this).length > 0
      $li.toggleClass('menu-open', shouldOpen)
      var panel = $li.children('.nav-treeview').get(0)
      if (!panel) return
      if (shouldOpen) {
        panel.style.display = 'block'
        panel.style.height = 'auto'
        panel.style.overflow = 'visible'
      } else {
        panel.style.display = 'none'
        panel.style.removeProperty('height')
        panel.style.removeProperty('overflow')
      }
    })

    fixOpenTreeviewLayout()
  }

  function rebindSidebarTreeview () {
    $(document).off('click', LINK_SELECTOR)

    $(document).on('click', LINK_SELECTOR, function (e) {
      var $link = $(this)
      var $li = $link.parent('li')

      // Real page links: navigate (or let tabs.js handle on the shell); do not run treeview toggle.
      if (!$li.hasClass('has-treeview')) {
        return
      }

      var inst = $link.closest(SIDEBAR_SELECTOR).data('lte.treeview')
      if (!inst) {
        return
      }

      e.preventDefault()
      inst.toggle(e)
      // After toggle (incl. animationSpeed 0), normalize open submenu layout
      window.setTimeout(function () {
        fixOpenTreeviewLayout($link.closest(SIDEBAR_SELECTOR))
      }, 0)
    })
  }

  $(function () {
    patchTreeviewAccordion()
    rebindSidebarTreeview()
    openActiveMenuAncestors()
    bindMiniSidebarHoverCollapse()
  })

  // Expose for tab shell sidebar sync
  window.MZSidebarTree = {
    fixOpenTreeviewLayout: fixOpenTreeviewLayout,
    openActiveMenuAncestors: openActiveMenuAncestors,
    clearSidebarFocusLock: clearSidebarFocusLock
  }
})(jQuery)
