<?php
/**
 * Browser-style tab bar — shown only on the tab shell (app.php).
 */
if (!mz_is_tab_shell()) {
	return;
}
?>
<div class="mz-tab-bar" id="mzTabBar" role="tablist" aria-label="Open pages">
	<button type="button" class="mz-tab-scroll mz-tab-scroll-left" id="mzTabScrollLeft" title="Scroll tabs" aria-label="Scroll tabs left" hidden>
		<i class="fas fa-chevron-left"></i>
	</button>
	<div class="mz-tab-list" id="mzTabList"></div>
	<button type="button" class="mz-tab-scroll mz-tab-scroll-right" id="mzTabScrollRight" title="Scroll tabs" aria-label="Scroll tabs right" hidden>
		<i class="fas fa-chevron-right"></i>
	</button>
</div>
<div class="mz-tab-panes" id="mzTabPanes" role="presentation"></div>
