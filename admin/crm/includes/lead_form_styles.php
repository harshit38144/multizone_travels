<?php
/** @var string $leadFormScope CSS scope prefix e.g. .crm-create-lead or #leadFormModal .crm-lead-form-embed */
$scope = $leadFormScope ?? '.crm-create-lead';
if (!defined('CRM_LEAD_JQUERY_UI_CSS')) {
    define('CRM_LEAD_JQUERY_UI_CSS', true);
    echo '<link rel="stylesheet" href="plugins/jquery-ui/jquery-ui.min.css">' . "\n";
}
?>
<style>
<?= $scope ?> .crm-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    overflow: visible;
    margin-bottom: 1rem;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}
<?= $scope ?> .crm-card-bd {
    overflow: visible;
}
<?= $scope ?> .crm-card-hd-blue,
<?= $scope ?> .crm-card-hd-teal,
<?= $scope ?> .crm-card-hd-green {
    background: #fff;
    color: #111827;
    font-weight: 700;
    padding: 0.85rem 1.15rem 0.7rem;
    font-size: 1rem;
    border-bottom: 1px solid #f1f5f9;
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.2rem;
}
<?= $scope ?> .crm-card-hd-blue .crm-card-hd-title,
<?= $scope ?> .crm-card-hd-teal .crm-card-hd-title,
<?= $scope ?> .crm-card-hd-green .crm-card-hd-title {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    position: relative;
    padding-bottom: 0.35rem;
}
<?= $scope ?> .crm-card-hd-blue .crm-card-hd-title::after,
<?= $scope ?> .crm-card-hd-teal .crm-card-hd-title::after,
<?= $scope ?> .crm-card-hd-green .crm-card-hd-title::after {
    content: "";
    position: absolute;
    left: 0;
    bottom: 0;
    width: 2.4rem;
    height: 3px;
    background: #e11d2e;
    border-radius: 999px;
}
<?= $scope ?> .crm-card-hd-blue .crm-card-hd-title i,
<?= $scope ?> .crm-card-hd-teal .crm-card-hd-title i,
<?= $scope ?> .crm-card-hd-green .crm-card-hd-title i {
    color: #e11d2e;
    font-size: 0.95rem;
}
<?= $scope ?> .crm-card-hd-sub {
    margin: 0.15rem 0 0;
    color: #94a3b8;
    font-size: 0.8rem;
    font-weight: 500;
    line-height: 1.35;
}
<?= $scope ?> .itinerary-card-hd {
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}
<?= $scope ?> .itinerary-card-hd .js-itinerary-collapse-toggle {
    border: 0;
    background: #f1f5f9;
    color: #64748b;
    min-width: 28px;
    height: 28px;
    padding: 0;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
<?= $scope ?> .itinerary-card-hd .js-itinerary-collapse-toggle:hover,
<?= $scope ?> .itinerary-card-hd .js-itinerary-collapse-toggle:focus {
    background: #e2e8f0;
    color: #334155;
    box-shadow: none;
}
<?= $scope ?> .crm-card-bd {
    padding: 1rem 1.15rem 1.15rem;
}
<?= $scope ?> .label-req::after {
    content: " *";
    color: #e11d2e;
    font-weight: 700;
}
<?= $scope ?> .form-group > label {
    font-weight: 600;
    color: #374151;
    font-size: 0.84rem;
    margin-bottom: 0.35rem;
}
<?= $scope ?> .form-control {
    border-color: #d1d5db;
    border-radius: 6px;
    color: #111827;
    font-size: 0.9rem;
    min-height: calc(2.25rem + 2px);
}
<?= $scope ?> .form-control:focus {
    border-color: #fca5a5;
    box-shadow: 0 0 0 0.15rem rgba(225, 29, 46, 0.12);
}
<?= $scope ?> .lead-field-icon {
    position: relative;
}
<?= $scope ?> .lead-field-icon > .form-control,
<?= $scope ?> .lead-field-icon > .lead-contact-combobox > .form-control,
<?= $scope ?> .lead-field-icon > select.form-control {
    padding-left: 2.15rem;
}
<?= $scope ?> .lead-field-icon.has-end-icon > .form-control,
<?= $scope ?> .lead-field-icon.has-end-icon > .lead-contact-combobox > .form-control {
    padding-left: 0.75rem;
    padding-right: 2.15rem;
}
<?= $scope ?> .lead-field-icon-glyph {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    left: 0.7rem;
    color: #9ca3af;
    font-size: 0.85rem;
    z-index: 2;
    pointer-events: none;
}
<?= $scope ?> .lead-field-icon.has-end-icon .lead-field-icon-glyph {
    left: auto;
    right: 0.7rem;
    color: #e11d2e;
}
<?= $scope ?> .form-label-bold {
    font-weight: 600;
    color: #212529;
}
<?= $scope ?> .text-hint {
    font-size: 0.8rem;
    color: #6c757d;
    margin-top: 0.25rem;
}
<?= $scope ?> .text-valid {
    font-size: 0.8rem;
    color: #28a745;
    margin-top: 0.2rem;
}
<?= $scope ?> .btn-teal {
    background: #17a2b8;
    border-color: #17a2b8;
    color: #fff;
    font-weight: 600;
}
<?= $scope ?> .btn-teal:hover {
    background: #138496;
    border-color: #117a8b;
    color: #fff;
}
<?= $scope ?> .traveler-grid {
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 1rem;
    background: #fafbfc;
    margin-bottom: 1rem;
}
<?= $scope ?> .tp-rg-picker {
    position: relative;
}
<?= $scope ?> .tp-rg-trigger {
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: pointer;
    background: #fff;
    min-height: calc(2.25rem + 2px);
}
<?= $scope ?> .tp-rg-trigger:focus {
    border-color: #80bdff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.15);
}
<?= $scope ?> .tp-rg-trigger-icon {
    color: #6c757d;
    font-size: 0.8rem;
}
<?= $scope ?> .tp-rg-panel {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    z-index: 1055;
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 10px;
    box-shadow: 0 0.75rem 1.5rem rgba(0, 0, 0, 0.12);
}
<?= $scope ?> .tp-rg-panel-hd {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.85rem 1rem;
    border-bottom: 1px solid #eef1f4;
    font-size: 1rem;
}
<?= $scope ?> .tp-rg-close {
    border: 0;
    background: transparent;
    color: #6c757d;
    font-size: 1.35rem;
    line-height: 1;
    padding: 0;
    cursor: pointer;
}
<?= $scope ?> .tp-rg-panel-bd {
    padding: 0.35rem 0;
}
<?= $scope ?> .tp-rg-panels-wrap {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    z-index: 1055;
}
<?= $scope ?> .tp-rg-panels-wrap .tp-rg-panel {
    position: relative;
    top: auto;
    left: auto;
    right: auto;
    width: 100%;
    min-width: 0;
}
<?= $scope ?> .tp-rg-child-ages-popup {
    position: absolute;
    top: 0;
    left: calc(100% + 10px);
    min-width: 270px;
    max-width: 300px;
    z-index: 1060;
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 10px;
    box-shadow: 0 0.75rem 1.5rem rgba(0, 0, 0, 0.12);
}
<?= $scope ?> .tp-rg-panels-wrap:not(.has-child-ages) .tp-rg-child-ages-popup {
    display: none !important;
}
<?= $scope ?> .tp-rg-child-ages-popup-hd {
    padding: 0.85rem 1rem;
    border-bottom: 1px solid #eef1f4;
    font-size: 1rem;
    font-weight: 700;
    color: #212529;
}
<?= $scope ?> .tp-rg-child-ages-popup-bd {
    padding: 0.85rem 1rem;
    background: #f3f4f6;
    border-radius: 0 0 10px 10px;
}
<?= $scope ?> .tp-rg-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.85rem 1rem;
}
<?= $scope ?> .tp-rg-row-label strong {
    display: block;
    font-size: 0.95rem;
    color: #212529;
}
<?= $scope ?> .tp-rg-row-label small {
    display: block;
    color: #6c757d;
    font-size: 0.78rem;
    margin-top: 0.1rem;
}
<?= $scope ?> .tp-rg-stepper {
    display: inline-flex;
    align-items: center;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    overflow: hidden;
    min-width: 108px;
    background: #fff;
}
<?= $scope ?> .tp-rg-step-btn {
    width: 34px;
    height: 34px;
    border: 0;
    background: #fff;
    color: #6b7280;
    font-size: 1.1rem;
    line-height: 1;
    cursor: pointer;
}
<?= $scope ?> .tp-rg-step-btn:hover:not(:disabled) {
    background: #fff5f5;
    color: #e11d2e;
}
<?= $scope ?> .tp-rg-step-btn:disabled {
    color: #adb5bd;
    cursor: not-allowed;
}
<?= $scope ?> .tp-rg-step-val {
    min-width: 34px;
    text-align: center;
    font-weight: 700;
    color: #e11d2e;
    font-size: 0.95rem;
}
<?= $scope ?> .tp-rg-step-input {
    width: 42px;
    min-width: 34px;
    max-width: 52px;
    border: 0;
    text-align: center;
    font-weight: 700;
    color: #e11d2e;
    font-size: 0.95rem;
    background: #fff;
    padding: 0.35rem 0.15rem;
    -moz-appearance: textfield;
    appearance: textfield;
}
<?= $scope ?> .tp-rg-step-input::-webkit-outer-spin-button,
<?= $scope ?> .tp-rg-step-input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
<?= $scope ?> .tp-rg-step-input:focus {
    outline: none;
    box-shadow: none;
}
<?= $scope ?> .tp-rooms-stepper .tp-rg-stepper {
    width: 118px;
    max-width: 100%;
    min-width: 0;
    justify-content: space-between;
    border-color: #fca5a5;
}
<?= $scope ?> .tp-nights-stepper .tp-rg-stepper {
    width: 118px;
    max-width: 100%;
    min-width: 0;
    justify-content: space-between;
}
<?= $scope ?> .tp-rooms-suggest-note {
    font-size: 0.68rem;
    line-height: 1.3;
    margin-top: 0.35rem;
}
<?= $scope ?> .tp-child-bed-field > label {
    font-size: 0.875rem;
    margin-bottom: 0.35rem;
}
<?= $scope ?> .tp-child-bed-list.form-row {
    margin-right: -0.5rem;
    margin-left: -0.5rem;
}
<?= $scope ?> .tp-child-bed-list.form-row > .tp-child-bed-row {
    padding-right: 0.5rem;
    padding-left: 0.5rem;
}
<?= $scope ?> .tp-child-bed-row {
    display: flex;
    flex-direction: column;
    align-items: stretch;
}
<?= $scope ?> .tp-child-bed-row-lbl {
    font-size: 0.8125rem;
    font-weight: 600;
    color: #495057;
    line-height: 1.25;
    margin-bottom: 0.3rem;
}
<?= $scope ?> .tp-child-bed-row .form-control {
    width: 100%;
    min-width: 0;
    height: calc(2.25rem + 2px);
    padding: 0.375rem 0.65rem;
    font-size: 0.875rem;
    line-height: 1.5;
}
<?= $scope ?> .tp-rg-child-ages {
    margin: 0 1rem 0.75rem;
    padding: 0.85rem;
    background: #f3f4f6;
    border-radius: 8px;
}
<?= $scope ?> .tp-rg-child-ages-popup .tp-rg-child-age-row:last-of-type {
    margin-bottom: 0.5rem;
}
<?= $scope ?> .tp-rg-child-ages-popup .tp-rg-child-ages-note {
    margin-top: 0.65rem;
}
<?= $scope ?> .tp-rg-child-ages-hd {
    font-weight: 700;
    margin-bottom: 0.65rem;
    color: #212529;
}
<?= $scope ?> .tp-rg-child-age-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.55rem;
}
<?= $scope ?> .tp-rg-child-age-row:last-of-type {
    margin-bottom: 0.75rem;
}
<?= $scope ?> .tp-rg-child-age-row label {
    margin: 0;
    font-weight: 600;
    color: #343a40;
    font-size: 0.9rem;
}
<?= $scope ?> .tp-rg-child-age-select {
    min-width: 92px;
    border: 1px solid #8ec5ff;
    border-radius: 999px;
    padding: 0.35rem 0.9rem;
    color: #007bff;
    font-weight: 600;
    background: #fff;
    appearance: auto;
}
<?= $scope ?> .tp-rg-child-ages-note {
    margin: 0;
    font-size: 0.75rem;
    color: #6c757d;
    line-height: 1.4;
}
<?= $scope ?> .tp-rg-add-pets {
    border: 1px solid #8ec5ff;
    color: #007bff;
    font-weight: 700;
    border-radius: 999px;
    min-width: 72px;
    background: #fff;
}
<?= $scope ?> .tp-rg-add-pets:hover {
    background: #f0f7ff;
    color: #007bff;
}
<?= $scope ?> .traveler-grid .form-group {
    margin-bottom: 0.75rem;
}
<?= $scope ?> .traveler-grid .form-group label {
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
}
<?= $scope ?> .traveler-legend {
    font-size: 0.75rem;
    color: #6c757d;
    margin-top: 0.5rem;
    margin-bottom: 0;
}
<?= $scope ?> .travelers-summary-box {
    background: #f1f3f5;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 0.5rem 0.75rem;
    font-weight: 600;
    color: #343a40;
}
<?= $scope ?> .svc-check-row {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.55rem;
    margin-bottom: 0;
}
<?= $scope ?> .svc-check-row .svc-tile {
    margin: 0;
    min-width: 0;
}
<?= $scope ?> .svc-tile {
    position: relative;
}
<?= $scope ?> .svc-tile-input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}
<?= $scope ?> .svc-tile-label {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    margin: 0;
    padding: 0.62rem 0.55rem;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: #fff;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.78rem;
    color: #374151;
    line-height: 1.25;
    min-height: 44px;
    transition: border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
}
<?= $scope ?> .svc-tile-label i {
    color: #9ca3af;
    width: 1rem;
    text-align: center;
    flex: 0 0 auto;
    font-size: 0.85rem;
}
<?= $scope ?> .svc-tile-label span.svc-tile-text {
    flex: 1 1 auto;
    min-width: 0;
}
<?= $scope ?> .svc-tile-box {
    width: 16px;
    height: 16px;
    border: 1.5px solid #d1d5db;
    border-radius: 4px;
    flex: 0 0 16px;
    background: #fff;
    position: relative;
}
<?= $scope ?> .svc-tile-input:checked + .svc-tile-label {
    border-color: #fca5a5;
    background: #fff8f8;
}
<?= $scope ?> .svc-tile-input:checked + .svc-tile-label i {
    color: #e11d2e;
}
<?= $scope ?> .svc-tile-input:checked + .svc-tile-label .svc-tile-box {
    background: #e11d2e;
    border-color: #e11d2e;
}
<?= $scope ?> .svc-tile-input:checked + .svc-tile-label .svc-tile-box::after {
    content: "";
    position: absolute;
    left: 4px;
    top: 1px;
    width: 5px;
    height: 9px;
    border: solid #fff;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
}
@media (max-width: 991px) {
<?= $scope ?> .svc-check-row {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 575px) {
<?= $scope ?> .svc-check-row {
        grid-template-columns: 1fr;
    }
}
<?= $scope ?> .svc-check-row .custom-control {
    min-width: 140px;
}
<?= $scope ?> .svc-check-row label {
    font-weight: 500;
}
<?= $scope ?> .itinerary-row .btn-add-mini {
    font-size: 0.7rem;
    padding: 0.1rem 0.4rem;
    line-height: 1.3;
    vertical-align: middle;
}
<?= $scope ?> .itinerary-row .lbl-inline {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    margin-bottom: 0.35rem;
}
<?= $scope ?> .itinerary-section-hd {
    font-size: 0.95rem;
    font-weight: 700;
    color: #343a40;
    margin-bottom: 0.45rem;
}
<?= $scope ?> .itinerary-section-hd-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.35rem;
    flex-wrap: wrap;
}
<?= $scope ?> .itinerary-total-nights-wrap {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.86rem;
    font-weight: 600;
    color: #495057;
}
<?= $scope ?> .itinerary-total-nights-wrap .js-itinerary-total-nights {
    width: 82px;
    min-width: 82px;
    height: 28px;
    padding: 0.15rem 0.4rem;
}
<?= $scope ?> .itinerary-dest-row,
<?= $scope ?> .itinerary-day-row {
    padding: 0.45rem 0.6rem;
    margin-bottom: 0.35rem;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 6px;
}
<?= $scope ?> .js-itinerary-card .crm-card-bd {
    padding: 0.8rem 1rem;
}
<?= $scope ?> .js-itinerary-card .itinerary-section {
    margin-bottom: 0.45rem;
}
<?= $scope ?> .js-itinerary-card .itinerary-section:last-child {
    margin-bottom: 0;
}
<?= $scope ?> .js-itinerary-card .itinerary-dest-row .form-group,
<?= $scope ?> .js-itinerary-card .itinerary-day-row .form-group {
    margin-bottom: 0.25rem;
}
<?= $scope ?> .itinerary-dest-row-compact {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.4rem;
    margin: 0 0.45rem 0.35rem 0;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 999px;
}
<?= $scope ?> .itinerary-dest-row-compact .itinerary-dest-label {
    font-weight: 600;
    color: #212529;
}
<?= $scope ?> .itinerary-dest-row-compact .itinerary-dest-sep,
<?= $scope ?> .itinerary-dest-row-compact .itinerary-dest-night-suffix {
    color: #6c757d;
    font-size: 0.82rem;
}
<?= $scope ?> .itinerary-dest-row-compact .itinerary-dest-night-input {
    width: 56px;
    min-width: 56px;
    height: 26px;
    padding: 0.1rem 0.35rem;
    text-align: center;
    font-size: 0.84rem;
}
<?= $scope ?> .itinerary-dest-name {
    min-height: calc(2.25rem + 2px);
    padding: 0.375rem 0.75rem;
    background: #fff;
    border: 1px solid #ced4da;
    border-radius: 0.25rem;
    font-weight: 600;
    color: #495057;
}
<?= $scope ?> .itinerary-day-badge {
    min-height: calc(2.25rem + 2px);
    display: flex;
    align-items: center;
    font-weight: 700;
    color: #007bff;
}
<?= $scope ?> .itinerary-summary {
    padding: 0.85rem 1rem;
    background: #eef5ff;
    border: 1px solid #cfe2ff;
    border-radius: 8px;
}
<?= $scope ?> .itinerary-summary-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem 1.5rem;
    margin-bottom: 0.65rem;
}
<?= $scope ?> .itinerary-summary-item {
    min-width: 110px;
}
<?= $scope ?> .itinerary-summary-label {
    display: block;
    font-size: 0.78rem;
    color: #6c757d;
    margin-bottom: 0.15rem;
}
<?= $scope ?> .itinerary-summary-foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
}
<?= $scope ?> .itinerary-summary-status.is-ok {
    color: #198754 !important;
}
<?= $scope ?> .itinerary-summary-status.is-warn {
    color: #856404 !important;
}
<?= $scope ?> .itinerary-day-dest-badge {
    min-height: calc(2.25rem + 2px);
    display: flex;
    align-items: center;
    padding: 0.375rem 0.75rem;
    background: #fff;
    border: 1px solid #ced4da;
    border-radius: 0.25rem;
    font-weight: 600;
    color: #495057;
}
<?= $scope ?> .itinerary-dest-row .js-itinerary-dest-nights.is-warn {
    border-color: #ffc107;
    background: #fffdf5;
}
<?= $scope ?> .form-actions {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 0.85rem 1.15rem;
    display: flex;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 0.65rem;
    margin-top: 0.25rem;
}
<?= $scope ?> .form-actions .btn-cancel {
    background: #fff;
    border: 1px solid #d1d5db;
    color: #111827;
    font-weight: 600;
    border-radius: 8px;
    min-height: 40px;
    order: 1;
}
<?= $scope ?> .form-actions .btn-cancel:hover {
    background: #f9fafb;
    color: #000;
}
<?= $scope ?> .form-actions .js-lead-submit-btn,
<?= $scope ?> .form-actions .btn-primary {
    background: #e11d2e;
    border-color: #e11d2e;
    color: #fff;
    font-weight: 700;
    border-radius: 8px;
    min-height: 40px;
    padding-left: 1.15rem;
    padding-right: 1.15rem;
    order: 2;
    display: inline-flex;
    align-items: center;
}
<?= $scope ?> .form-actions .js-lead-submit-btn i {
    margin-left: 0.45rem;
    font-size: 0.78rem;
}
<?= $scope ?> .form-actions .js-lead-submit-btn:hover,
<?= $scope ?> .form-actions .btn-primary:hover,
<?= $scope ?> .form-actions .js-lead-submit-btn:focus,
<?= $scope ?> .form-actions .btn-primary:focus {
    background: #c81a28;
    border-color: #c81a28;
    color: #fff;
}
<?= $scope ?> .svc-detail-panel {
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 1rem 1.1rem;
    margin-bottom: 1rem;
    background: #fff;
}
<?= $scope ?> .svc-detail-panel:last-child {
    margin-bottom: 0;
}
<?= $scope ?> .svc-detail-hd {
    font-size: 0.95rem;
    font-weight: 700;
    color: #111827;
    margin: 0 0 0.95rem;
    padding-bottom: 0.55rem;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
<?= $scope ?> .svc-detail-hd i {
    color: #e11d2e;
}
<?= $scope ?> .travel-details-empty {
    padding: 1.5rem;
    text-align: center;
    background: #f8f9fa;
    border: 1px dashed #ced4da;
    border-radius: 6px;
    margin: 0;
}
<?= $scope ?> .js-travel-details-card {
    overflow: visible;
}
<?= $scope ?> .js-travel-details-card .crm-card-bd {
    overflow: visible;
}
<?= $scope ?> .js-travel-details-card .tp-hotel-cat-picker,
<?= $scope ?> .js-travel-details-card .tp-rg-picker {
    z-index: 1;
}
<?= $scope ?> .js-travel-details-card .tp-hotel-cat-picker:focus-within,
<?= $scope ?> .js-travel-details-card .tp-rg-picker:focus-within {
    z-index: 1080;
}
<?= $scope ?> .tp-destination-combobox {
    position: relative;
}
<?= $scope ?> .tp-destination-field {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.35rem;
    min-height: calc(2.25rem + 2px);
    padding: 0.25rem 0.5rem;
    border: 1px solid #ced4da;
    border-radius: 0.25rem;
    background: #fff;
    cursor: text;
}
<?= $scope ?> .tp-destination-field:focus-within {
    border-color: #80bdff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.15);
}
<?= $scope ?> .tp-destination-field.is-disabled {
    background-color: #e9ecef;
    cursor: not-allowed;
}
<?= $scope ?> .tp-destination-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}
<?= $scope ?> .tp-destination-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    max-width: 100%;
    padding: 0.15rem 0.45rem;
    border-radius: 999px;
    background: #e7f1ff;
    color: #0d6efd;
    font-size: 0.82rem;
    line-height: 1.2;
}
<?= $scope ?> .tp-destination-tag-text {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
<?= $scope ?> .tp-destination-tag-remove {
    border: 0;
    background: transparent;
    color: #0d6efd;
    font-size: 1rem;
    line-height: 1;
    padding: 0;
    cursor: pointer;
}
<?= $scope ?> .tp-destination-tag-remove:hover {
    color: #084298;
}
<?= $scope ?> .tp-destination-search {
    flex: 1 1 120px;
    min-width: 120px;
    border: 0;
    outline: none;
    background: transparent;
    padding: 0.2rem 0.15rem;
    font-size: 1rem;
    color: #495057;
}
<?= $scope ?> .tp-destination-search:disabled {
    background: transparent;
    cursor: not-allowed;
}
<?= $scope ?> .tp-destination-menu {
    position: absolute;
    top: calc(100% + 2px);
    left: 0;
    right: 0;
    z-index: 1060;
    max-height: 220px;
    overflow-y: auto;
    background: #fff;
    border: 1px solid #ced4da;
    border-radius: 0.25rem;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.12);
}
<?= $scope ?> .tp-destination-item {
    display: block;
    width: 100%;
    padding: 0.55rem 0.75rem;
    border: 0;
    background: transparent;
    color: #212529;
    text-align: left;
    cursor: pointer;
}
<?= $scope ?> .tp-destination-item:hover,
<?= $scope ?> .tp-destination-item:focus {
    background: #f1f3f5;
    outline: none;
}
<?= $scope ?> .tp-destination-empty {
    padding: 0.65rem 0.75rem;
    color: #6c757d;
    font-size: 0.9rem;
}
<?= $scope ?> .tp-destination-divider {
    border-top: 1px solid #e9ecef;
    margin: 0.25rem 0;
}
<?= $scope ?> .tp-destination-item-create {
    color: #007bff;
    font-weight: 600;
}
<?= $scope ?> .tp-destination-item-create:hover,
<?= $scope ?> .tp-destination-item-create:focus {
    background: #eef5ff;
}
<?= $scope ?> .tp-hotel-cat-picker {
    position: relative;
}
<?= $scope ?> .tp-hotel-cat-field {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.35rem;
    min-height: calc(2.25rem + 2px);
    padding: 0.25rem 0.5rem;
    border: 1px solid #ced4da;
    border-radius: 0.25rem;
    background: #fff;
    cursor: pointer;
}
<?= $scope ?> .tp-hotel-cat-field:focus {
    outline: none;
    border-color: #80bdff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.15);
}
<?= $scope ?> .tp-hotel-cat-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}
<?= $scope ?> .tp-hotel-cat-tags:not(:empty) + .tp-hotel-cat-placeholder {
    display: none;
}
<?= $scope ?> .tp-hotel-cat-placeholder {
    color: #6c757d;
    font-size: 0.95rem;
    line-height: 1.5;
}
<?= $scope ?> .tp-hotel-cat-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.15rem 0.45rem;
    border-radius: 999px;
    background: #fff3cd;
    color: #856404;
    font-size: 0.82rem;
    line-height: 1.2;
}
<?= $scope ?> .tp-hotel-cat-tag-remove {
    border: 0;
    background: transparent;
    color: inherit;
    font-size: 1rem;
    line-height: 1;
    padding: 0;
    cursor: pointer;
    opacity: 0.75;
}
<?= $scope ?> .tp-hotel-cat-tag-remove:hover {
    opacity: 1;
}
<?= $scope ?> .tp-hotel-cat-menu {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    z-index: 1055;
    max-height: 220px;
    overflow-y: auto;
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 0.25rem;
    box-shadow: 0 0.75rem 1.5rem rgba(0, 0, 0, 0.12);
}
<?= $scope ?> .tp-hotel-cat-item {
    display: block;
    width: 100%;
    border: 0;
    background: transparent;
    text-align: left;
    padding: 0.55rem 0.85rem;
    color: #212529;
    cursor: pointer;
}
<?= $scope ?> .tp-hotel-cat-item:hover,
<?= $scope ?> .tp-hotel-cat-item:focus {
    background: #fff8e1;
    outline: none;
}
<?= $scope ?> .tp-hotel-cat-item.is-active {
    background: #fff3cd;
    font-weight: 600;
}
<?= $scope ?> .tp-hotel-cat-empty {
    padding: 0.65rem 0.85rem;
    color: #6c757d;
    font-size: 0.88rem;
}
<?= $scope ?> .tp-vehicle-type-picker {
    position: relative;
}
<?= $scope ?> .tp-vehicle-type-field {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.35rem;
    min-height: calc(2.25rem + 2px);
    padding: 0.25rem 0.5rem;
    border: 1px solid #ced4da;
    border-radius: 0.25rem;
    background: #fff;
    cursor: pointer;
}
<?= $scope ?> .tp-vehicle-type-field:focus {
    outline: none;
    border-color: #80bdff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.15);
}
<?= $scope ?> .tp-vehicle-type-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}
<?= $scope ?> .tp-vehicle-type-tags:not(:empty) + .tp-vehicle-type-placeholder {
    display: none;
}
<?= $scope ?> .tp-vehicle-type-placeholder {
    color: #6c757d;
    font-size: 0.95rem;
    line-height: 1.5;
}
<?= $scope ?> .tp-vehicle-type-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.15rem 0.45rem;
    border-radius: 999px;
    background: #e8f4fd;
    color: #0c5460;
    font-size: 0.82rem;
    line-height: 1.2;
}
<?= $scope ?> .tp-vehicle-type-tag-remove {
    border: 0;
    background: transparent;
    color: inherit;
    font-size: 1rem;
    line-height: 1;
    padding: 0;
    cursor: pointer;
    opacity: 0.75;
}
<?= $scope ?> .tp-vehicle-type-tag-remove:hover {
    opacity: 1;
}
<?= $scope ?> .tp-vehicle-type-menu {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    z-index: 1055;
    max-height: 220px;
    overflow-y: auto;
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 0.25rem;
    box-shadow: 0 0.75rem 1.5rem rgba(0, 0, 0, 0.12);
}
<?= $scope ?> .tp-vehicle-type-item {
    display: block;
    width: 100%;
    border: 0;
    background: transparent;
    text-align: left;
    padding: 0.55rem 0.85rem;
    color: #212529;
    cursor: pointer;
}
<?= $scope ?> .tp-vehicle-type-item:hover,
<?= $scope ?> .tp-vehicle-type-item:focus {
    background: #eef5ff;
    outline: none;
}
<?= $scope ?> .tp-vehicle-type-empty {
    padding: 0.65rem 0.85rem;
    color: #6c757d;
    font-size: 0.88rem;
}

.tp-dest-create-modal {
    z-index: 1060 !important;
}
.tp-dest-create-modal .tp-dest-create-hd {
    background: linear-gradient(135deg, #6a11cb 0%, #7b5ea7 50%, #a855f7 100%);
    color: #fff;
}
.tp-dest-create-modal .tp-dest-create-bd {
    max-height: calc(100vh - 220px);
    overflow-y: auto;
    background: #f8f9fa;
}
.tp-dest-create-modal .tp-dest-create-ft {
    background: #fff;
    border-top: 1px solid #dee2e6;
}
.tp-dest-create-modal .tp-dest-create-side-card {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, .05);
}
.tp-dest-create-modal .tp-dest-create-side-hd {
    background: linear-gradient(135deg, #6a11cb 0%, #7b5ea7 50%, #a855f7 100%);
    color: #fff;
    font-weight: 600;
    padding: 0.65rem 0.9rem;
    font-size: 0.95rem;
}
.tp-dest-create-modal .tp-dest-create-side-bd {
    padding: 0.9rem;
}

.crm-lead-datepicker.ui-datepicker {
    z-index: 2000 !important;
    width: 292px;
    padding: 0.85rem 0.9rem 0.75rem;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    box-shadow: 0 12px 32px rgba(15, 23, 42, 0.14);
    font-size: 0.875rem;
    font-family: inherit;
    background: #fff;
}
.crm-lead-datepicker.ui-datepicker .ui-widget-header {
    background: #fff !important;
    border: 0 !important;
    color: #111827 !important;
}
.crm-lead-datepicker .ui-datepicker-header {
    display: grid;
    grid-template-columns: 34px minmax(72px, 1fr) minmax(82px, 1fr) 34px;
    align-items: center;
    column-gap: 0.5rem;
    background: #fff;
    border: 0;
    border-bottom: 1px solid #eef2f7;
    border-radius: 0;
    padding: 0 0 0.75rem;
    margin-bottom: 0.65rem;
    position: relative;
}
.crm-lead-datepicker .ui-datepicker-prev,
.crm-lead-datepicker .ui-datepicker-next {
    position: static;
    top: auto;
    left: auto;
    right: auto;
    width: 34px;
    height: 34px;
    border: 1px solid #e5e7eb;
    border-radius: 50%;
    background: #fff;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    justify-self: center;
    text-decoration: none;
}
.crm-lead-datepicker .ui-datepicker-prev {
    grid-column: 1;
    grid-row: 1;
}
.crm-lead-datepicker .ui-datepicker-next {
    grid-column: 4;
    grid-row: 1;
}
.crm-lead-datepicker .ui-datepicker-prev-hover,
.crm-lead-datepicker .ui-datepicker-next-hover {
    top: auto;
    left: auto;
    right: auto;
    background: #f8fafc;
    border-color: #cbd5e1;
}
.crm-lead-datepicker .ui-datepicker-prev span,
.crm-lead-datepicker .ui-datepicker-next span {
    position: static;
    margin: 0;
    display: block;
    width: 100%;
    height: 100%;
    text-indent: -9999px;
    overflow: hidden;
    pointer-events: none;
    background-position: center;
    background-repeat: no-repeat;
    background-size: 14px 14px;
}
.crm-lead-datepicker .ui-datepicker-prev span {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23374151' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='15 18 9 12 15 6'/%3E%3C/svg%3E");
}
.crm-lead-datepicker .ui-datepicker-next span {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23374151' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='9 18 15 12 9 6'/%3E%3C/svg%3E");
}
.crm-lead-datepicker .ui-datepicker-title {
    display: contents;
}
.crm-lead-datepicker .ui-datepicker-title select,
.crm-lead-datepicker select.ui-datepicker-month,
.crm-lead-datepicker select.ui-datepicker-year {
    appearance: auto;
    width: 100%;
    font-size: 0.92rem;
    font-weight: 600;
    color: #111827;
    margin: 0;
    padding: 0.42rem 1.65rem 0.42rem 0.7rem;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    background: #fff;
    min-height: 34px;
    line-height: 1.2;
    box-shadow: none;
    cursor: pointer;
    grid-row: 1;
}
.crm-lead-datepicker select.ui-datepicker-month {
    grid-column: 2;
}
.crm-lead-datepicker select.ui-datepicker-year {
    grid-column: 3;
}
.crm-lead-datepicker .ui-datepicker-title select:focus,
.crm-lead-datepicker select.ui-datepicker-month:focus,
.crm-lead-datepicker select.ui-datepicker-year:focus {
    outline: none;
    border-color: #99f6e4;
    box-shadow: 0 0 0 3px rgba(45, 212, 191, 0.18);
}
.crm-lead-datepicker .ui-datepicker-calendar {
    width: 100%;
    margin: 0;
    border-collapse: separate;
    border-spacing: 0 2px;
}
.crm-lead-datepicker .ui-datepicker-calendar thead th {
    color: #94a3b8;
    font-weight: 700;
    font-size: 0.72rem;
    letter-spacing: 0.02em;
    padding: 0.15rem 0 0.45rem;
    text-transform: uppercase;
    border: 0;
    background: transparent;
}
.crm-lead-datepicker .ui-datepicker-calendar td {
    padding: 1px;
    border: 0;
    text-align: center;
}
.crm-lead-datepicker .ui-datepicker-calendar td a,
.crm-lead-datepicker .ui-datepicker-calendar td span {
    width: 34px;
    height: 34px;
    line-height: 34px;
    padding: 0;
    margin: 0 auto;
    border: none !important;
    background: transparent !important;
    color: #111827 !important;
    font-weight: 700;
    font-size: 0.92rem;
    border-radius: 50%;
    text-align: center;
    display: inline-block;
    box-shadow: none !important;
}
.crm-lead-datepicker .ui-datepicker-calendar td a.ui-state-hover {
    background: #f3f4f6 !important;
    color: #111827 !important;
}
.crm-lead-datepicker .ui-datepicker-other-month a,
.crm-lead-datepicker .ui-datepicker-other-month span {
    color: #cbd5e1 !important;
    font-weight: 600;
}
.crm-lead-datepicker .ui-datepicker-calendar td .ui-state-highlight {
    background: #ecfdf5 !important;
    color: #059669 !important;
    border: none !important;
}
.crm-lead-datepicker .ui-datepicker-calendar td .ui-state-active,
.crm-lead-datepicker .ui-datepicker-calendar td .ui-state-active.ui-state-highlight {
    background: #10b981 !important;
    color: #fff !important;
    border: none !important;
}
.crm-lead-datepicker .ui-datepicker-buttonpane {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 0.5rem;
    margin: 0.75rem 0 0;
    padding: 0.75rem 0 0;
    border-top: 1px solid #eef2f7;
    background: transparent;
}
.crm-lead-datepicker .ui-datepicker-buttonpane button {
    margin: 0;
    padding: 0;
    border: 0;
    background: transparent;
    font: inherit;
}
.crm-lead-datepicker .ui-datepicker-current {
    display: none !important;
}
.crm-lead-datepicker .ui-datepicker-close {
    background: #10b981 !important;
    color: #fff !important;
    border: none !important;
    border-radius: 999px !important;
    padding: 0.45rem 1.35rem !important;
    font-size: 0.92rem !important;
    font-weight: 700 !important;
    line-height: 1.2 !important;
    cursor: pointer;
    box-shadow: none !important;
}
.crm-lead-datepicker .ui-datepicker-close:hover,
.crm-lead-datepicker .ui-datepicker-close:focus {
    background: #059669 !important;
    color: #fff !important;
}
<?= $scope ?> input.js-lead-date-input {
    background-color: #fff;
}
<?= $scope ?> .lead-date-input-group .form-control {
    border-right: 0;
    background-color: #fff;
    cursor: pointer;
}
<?= $scope ?> .lead-date-input-group .form-control[readonly] {
    background-color: #fff;
}
<?= $scope ?> .lead-date-input-group .js-lead-date-trigger {
    border-color: #d1d5db;
    color: #6b7280;
    background: #fff;
    min-width: 2.5rem;
}
<?= $scope ?> .lead-date-input-group .js-lead-date-trigger:hover,
<?= $scope ?> .lead-date-input-group .js-lead-date-trigger:focus {
    border-color: #fca5a5;
    color: #e11d2e;
    background: #fff8f8;
    box-shadow: none;
}
<?= $scope ?> .lead-date-input-group .form-control:focus + .input-group-append .js-lead-date-trigger {
    border-color: #fca5a5;
    color: #e11d2e;
}

<?= $scope ?> .lead-contact-combobox {
    position: relative;
}
<?= $scope ?> .lead-contact-menu {
    position: absolute;
    top: calc(100% + 2px);
    left: 0;
    right: 0;
    z-index: 2060;
    max-height: 220px;
    overflow-y: auto;
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.12);
}
<?= $scope ?> .lead-contact-item {
    display: block;
    width: 100%;
    padding: 0.45rem 0.65rem;
    border: 0;
    background: transparent;
    color: #334155;
    text-align: left;
    cursor: pointer;
}
<?= $scope ?> .lead-contact-item:hover,
<?= $scope ?> .lead-contact-item:focus {
    background: #f1f5f9;
    outline: none;
}
<?= $scope ?> .lead-contact-item-title {
    display: block;
    font-weight: 600;
    font-size: 0.8125rem;
}
<?= $scope ?> .lead-contact-item-meta {
    display: block;
    font-size: 0.75rem;
    color: #64748b;
    margin-top: 0.1rem;
}
<?= $scope ?> .lead-contact-empty {
    padding: 0.55rem 0.65rem;
    font-size: 0.78rem;
    color: #64748b;
}

/* ——— Dark mode (Create Lead page + modal embed) ——— */
[data-theme="dark"] <?= $scope ?> .crm-card,
[data-theme="dark"] <?= $scope ?> .crm-card-hd-blue,
[data-theme="dark"] <?= $scope ?> .crm-card-hd-teal,
[data-theme="dark"] <?= $scope ?> .crm-card-hd-green,
[data-theme="dark"] <?= $scope ?> .crm-card-bd,
[data-theme="dark"] <?= $scope ?> .form-actions,
[data-theme="dark"] <?= $scope ?> .svc-detail-panel {
    background: var(--mz-theme-bg-surface, #22252d) !important;
    border-color: var(--mz-theme-border, #3a404d) !important;
    color: var(--mz-theme-text, #b8c0cc) !important;
    box-shadow: none !important;
}

[data-theme="dark"] <?= $scope ?> .crm-card-hd-blue,
[data-theme="dark"] <?= $scope ?> .crm-card-hd-teal,
[data-theme="dark"] <?= $scope ?> .crm-card-hd-green {
    border-bottom-color: var(--mz-theme-border, #3a404d) !important;
    color: var(--mz-theme-text, #b8c0cc) !important;
}

[data-theme="dark"] <?= $scope ?> .crm-card-hd-blue .crm-card-hd-title,
[data-theme="dark"] <?= $scope ?> .crm-card-hd-teal .crm-card-hd-title,
[data-theme="dark"] <?= $scope ?> .crm-card-hd-green .crm-card-hd-title {
    color: var(--mz-theme-text, #b8c0cc) !important;
}

[data-theme="dark"] <?= $scope ?> .crm-card-hd-blue .crm-card-hd-title i,
[data-theme="dark"] <?= $scope ?> .crm-card-hd-teal .crm-card-hd-title i,
[data-theme="dark"] <?= $scope ?> .crm-card-hd-green .crm-card-hd-title i,
[data-theme="dark"] <?= $scope ?> .svc-detail-hd i {
    color: #c98882 !important;
}

[data-theme="dark"] <?= $scope ?> .crm-card-hd-blue .crm-card-hd-title::after,
[data-theme="dark"] <?= $scope ?> .crm-card-hd-teal .crm-card-hd-title::after,
[data-theme="dark"] <?= $scope ?> .crm-card-hd-green .crm-card-hd-title::after {
    background: #c98882 !important;
}

[data-theme="dark"] <?= $scope ?> .crm-card-hd-sub,
[data-theme="dark"] <?= $scope ?> .text-hint,
[data-theme="dark"] <?= $scope ?> .lead-contact-empty,
[data-theme="dark"] <?= $scope ?> .lead-contact-item-meta {
    color: var(--mz-theme-text-muted, #7a8494) !important;
}

[data-theme="dark"] <?= $scope ?> .form-group > label,
[data-theme="dark"] <?= $scope ?> .form-label-bold,
[data-theme="dark"] <?= $scope ?> .svc-detail-hd,
[data-theme="dark"] <?= $scope ?> .tp-rg-row-label strong,
[data-theme="dark"] <?= $scope ?> .lead-contact-item-title {
    color: var(--mz-theme-text-secondary, #9aa3b2) !important;
}

[data-theme="dark"] <?= $scope ?> .form-control,
[data-theme="dark"] <?= $scope ?> .custom-select,
[data-theme="dark"] <?= $scope ?> .tp-rg-trigger,
[data-theme="dark"] <?= $scope ?> .tp-destination-field,
[data-theme="dark"] <?= $scope ?> .itinerary-day-dest-badge,
[data-theme="dark"] <?= $scope ?> select.form-control {
    background: var(--mz-theme-input-bg, #1e2128) !important;
    border-color: var(--mz-theme-input-border, #454b58) !important;
    color: var(--mz-theme-text, #b8c0cc) !important;
}

[data-theme="dark"] <?= $scope ?> .form-control:focus {
    border-color: rgba(201, 136, 130, 0.55) !important;
    box-shadow: 0 0 0 0.15rem rgba(201, 136, 130, 0.15) !important;
}

[data-theme="dark"] <?= $scope ?> .lead-field-icon-glyph {
    color: var(--mz-theme-text-muted, #7a8494) !important;
}

[data-theme="dark"] <?= $scope ?> .traveler-grid {
    background: var(--mz-theme-bg-elevated, #2a2e38) !important;
    border-color: var(--mz-theme-border, #3a404d) !important;
}

[data-theme="dark"] <?= $scope ?> .travel-details-empty {
    background: var(--mz-theme-bg-elevated, #2a2e38) !important;
    border-color: var(--mz-theme-border, #3a404d) !important;
    color: var(--mz-theme-text-muted, #7a8494) !important;
}

[data-theme="dark"] <?= $scope ?> .svc-tile-label {
    background: var(--mz-theme-bg-elevated, #2a2e38) !important;
    border-color: var(--mz-theme-border, #3a404d) !important;
    color: var(--mz-theme-text, #b8c0cc) !important;
}

[data-theme="dark"] <?= $scope ?> .svc-tile-label i {
    color: var(--mz-theme-text-muted, #7a8494) !important;
}

[data-theme="dark"] <?= $scope ?> .svc-tile-box {
    background: var(--mz-theme-input-bg, #1e2128) !important;
    border-color: var(--mz-theme-input-border, #454b58) !important;
}

[data-theme="dark"] <?= $scope ?> .svc-tile-input:checked + .svc-tile-label {
    background: rgba(201, 136, 130, 0.1) !important;
    border-color: rgba(201, 136, 130, 0.4) !important;
}

[data-theme="dark"] <?= $scope ?> .svc-tile-input:checked + .svc-tile-label i {
    color: #c98882 !important;
}

[data-theme="dark"] <?= $scope ?> .form-actions .btn-cancel {
    background: var(--mz-theme-bg-elevated, #2a2e38) !important;
    border-color: var(--mz-theme-border, #3a404d) !important;
    color: var(--mz-theme-text, #b8c0cc) !important;
}

[data-theme="dark"] <?= $scope ?> .form-actions .btn-cancel:hover {
    background: var(--mz-theme-bg-muted, #323744) !important;
    color: var(--mz-theme-text, #b8c0cc) !important;
}

[data-theme="dark"] <?= $scope ?> .tp-rg-panel,
[data-theme="dark"] <?= $scope ?> .tp-rg-child-ages-popup,
[data-theme="dark"] <?= $scope ?> .tp-destination-menu,
[data-theme="dark"] <?= $scope ?> .lead-contact-menu,
[data-theme="dark"] <?= $scope ?> .tp-rg-stepper {
    background: var(--mz-theme-bg-elevated, #2a2e38) !important;
    border-color: var(--mz-theme-border, #3a404d) !important;
    color: var(--mz-theme-text, #b8c0cc) !important;
}

[data-theme="dark"] <?= $scope ?> .tp-rg-child-ages-popup-bd {
    background: var(--mz-theme-bg-muted, #323744) !important;
}

[data-theme="dark"] <?= $scope ?> .tp-rg-child-ages-popup-hd,
[data-theme="dark"] <?= $scope ?> .tp-rg-row-label strong {
    color: var(--mz-theme-text, #b8c0cc) !important;
}

[data-theme="dark"] <?= $scope ?> .tp-rg-row-label small,
[data-theme="dark"] <?= $scope ?> .tp-rg-close {
    color: var(--mz-theme-text-muted, #7a8494) !important;
}

[data-theme="dark"] <?= $scope ?> .tp-rg-panel-hd,
[data-theme="dark"] <?= $scope ?> .svc-detail-hd {
    border-bottom-color: var(--mz-theme-border, #3a404d) !important;
}

[data-theme="dark"] <?= $scope ?> .lead-contact-item {
    color: var(--mz-theme-text, #b8c0cc) !important;
}

[data-theme="dark"] <?= $scope ?> .lead-contact-item:hover,
[data-theme="dark"] <?= $scope ?> .lead-contact-item:focus {
    background: var(--mz-theme-bg-muted, #323744) !important;
}

[data-theme="dark"] <?= $scope ?> .tp-destination-tag {
    background: rgba(139, 175, 212, 0.14) !important;
    color: #8bafd4 !important;
}

[data-theme="dark"] <?= $scope ?> .tp-destination-tag-remove {
    color: #8bafd4 !important;
}

[data-theme="dark"] <?= $scope ?> .tp-destination-search {
    color: var(--mz-theme-text, #b8c0cc) !important;
}

[data-theme="dark"] <?= $scope ?> .tp-destination-field.is-disabled {
    background: var(--mz-theme-bg-muted, #323744) !important;
}

[data-theme="dark"] <?= $scope ?> .itinerary-card-hd .js-itinerary-collapse-toggle {
    background: var(--mz-theme-bg-muted, #323744) !important;
    color: var(--mz-theme-text-muted, #7a8494) !important;
}
</style>
