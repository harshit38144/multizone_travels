/**
 * AdminLTE Treeview uses accordion: true by default. Nested menus under "Website" / "CRM"
 * need accordion OFF so opening Page Management does not close sibling branches —
 * and nested toggles must keep ancestor .menu-open. Also ensures data-accordion="false"
 * is applied as a real boolean on the plugin instance.
 */
(function ($) {
  'use strict'

  function patchTreeviewAccordion () {
    $('.nav-sidebar[data-widget="treeview"]').each(function () {
      var inst = $(this).data('lte.treeview')
      if (inst && inst._config) {
        inst._config.accordion = false
      }
    })
  }

  $(window).on('load', function () {
    patchTreeviewAccordion()
  })

  /**
   * Nested branch toggles only (link sits inside a .nav-treeview).
   * After AdminLTE runs, re-open **ancestor** groups only (Website, CRM, …) — never the branch you just
   * toggled — so expanding nested items keeps parents open, but closing Page Management / Leads still works.
   */
  $(document).on('click', '.nav-sidebar .nav-link[href="#"]', function () {
    var $link = $(this)
    if (!$link.closest('.nav-treeview').length) {
      return
    }
    var $branchLi = $link.closest('li.nav-item.has-treeview')
    window.setTimeout(function () {
      var $ancestors = $branchLi.parents('.nav-item.has-treeview')
      $ancestors.addClass('menu-open')
      $ancestors.each(function () {
        var $panel = $(this).children('.nav-treeview').first()
        if ($panel.length && $panel.is(':hidden')) {
          $panel.show()
        }
      })
    }, 320)
  })
})(jQuery)
