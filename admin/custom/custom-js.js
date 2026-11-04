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

  /** On page load only — expand parents of the current page link. */
  function openActiveMenuAncestors () {
    $('.nav-sidebar .nav-link.active').each(function () {
      $(this).parents('.nav-item.has-treeview').addClass('menu-open')
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
