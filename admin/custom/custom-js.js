/**
 * Admin sidebar: leaf links navigate without collapsing parents; branch toggles open/close normally.
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
      }
    })
  }

  /** On page load only — expand parents of the current page link. */
  function openActiveMenuAncestors () {
    $('.nav-sidebar .nav-link.active').each(function () {
      $(this).parents('.nav-item.has-treeview').addClass('menu-open')
    })
  }

  function rebindSidebarTreeview () {
    $(document).off('click', LINK_SELECTOR)

    $(document).on('click', LINK_SELECTOR, function (e) {
      var $link = $(this)
      var $li = $link.parent('li')

      // Real page links: navigate; do not run treeview toggle.
      if (!$li.hasClass('has-treeview')) {
        return
      }

      var inst = $link.closest(SIDEBAR_SELECTOR).data('lte.treeview')
      if (!inst) {
        return
      }

      e.preventDefault()
      inst.toggle(e)
    })
  }

  $(function () {
    patchTreeviewAccordion()
    rebindSidebarTreeview()
    openActiveMenuAncestors()
  })
})(jQuery)
