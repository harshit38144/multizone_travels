/**
 * Admin sidebar: leaf links navigate without collapsing parents; branch toggles open/close normally.
 * When the multi-tab shell is active, leaf navigation is handled by tabs.js instead.
 */
;(function ($) {
  'use strict'

  var SIDEBAR_SELECTOR = '.nav-sidebar[data-widget="treeview"]'
  var LINK_SELECTOR = '[data-widget="treeview"] .nav-link'

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
  })

  // Expose for tab shell sidebar sync
  window.MZSidebarTree = {
    fixOpenTreeviewLayout: fixOpenTreeviewLayout,
    openActiveMenuAncestors: openActiveMenuAncestors
  }
})(jQuery)
