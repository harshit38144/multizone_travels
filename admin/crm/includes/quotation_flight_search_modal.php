<?php
$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
?>
<!-- Search Flight modal (same UI as admin/etickets.php) -->
<div class="modal fade qfs-flight-search" id="qfsSearchModal" tabindex="-1" role="dialog" aria-labelledby="qfsSearchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 0;">
            <div class="modal-header qfs-search-hd">
                <h5 class="modal-title font-weight-bold" id="qfsSearchModalLabel">Search Flight</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body qfs-search-body">
                <div class="row mb-2">
                    <div class="col-md-3">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="qfs_flightType" id="qfsDomestic" value="domestic" checked>
                            <label class="form-check-label" for="qfsDomestic">Domestic</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="qfs_flightType" id="qfsInternational" value="international">
                            <label class="form-check-label" for="qfsInternational">International</label>
                        </div>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="qfs_tripType" id="qfsOneway" value="oneway" checked>
                            <label class="form-check-label" for="qfsOneway">One way</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="qfs_tripType" id="qfsRoundtrip" value="roundtrip">
                            <label class="form-check-label" for="qfsRoundtrip">Round Trip</label>
                        </div>
                    </div>
                </div>
                <div class="row align-items-end">
                    <div class="col-md-6 form-group">
                        <div class="qfs-route-row">
                            <div class="qfs-route-field qfs-airport-field">
                                <label class="font-weight-bold" for="qfsApiFrom">From</label>
                                <input type="text" class="form-control" id="qfsApiFrom" autocomplete="off" placeholder="Start typing a city...">
                                <div id="qfsApiFromSuggest" class="qfs-airport-suggest" style="display:none;"></div>
                            </div>
                            <div class="qfs-swap-wrap">
                                <label class="font-weight-bold">&nbsp;</label>
                                <button type="button" class="btn qfs-swap-btn" id="qfsSwapAirports" title="Swap From / To" aria-label="Swap From and To">
                                    <i class="fas fa-exchange-alt"></i>
                                </button>
                            </div>
                            <div class="qfs-route-field qfs-airport-field">
                                <label class="font-weight-bold" for="qfsApiTo">To</label>
                                <input type="text" class="form-control" id="qfsApiTo" autocomplete="off" placeholder="Start typing a city...">
                                <div id="qfsApiToSuggest" class="qfs-airport-suggest" style="display:none;"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 form-group qfs-date-field">
                        <label class="font-weight-bold">Onward Date</label>
                        <div class="qfs-date-wrapper">
                            <input type="date" class="form-control" id="qfsApiDate" value="<?= htmlspecialchars($today) ?>" min="<?= htmlspecialchars($today) ?>">
                            <img src="img/calendar.png" class="qfs-calendar-icon" alt="" onclick="qfsOpenDatePicker('qfsApiDate')">
                        </div>
                    </div>
                    <div class="col-md-3 form-group qfs-date-field" id="qfsReturnDateContainer" style="display:none;">
                        <label class="font-weight-bold">Return Date</label>
                        <div class="qfs-date-wrapper">
                            <input type="date" class="form-control" id="qfsApiReturnDate" value="<?= htmlspecialchars($tomorrow) ?>" min="<?= htmlspecialchars($today) ?>">
                            <img src="img/calendar.png" class="qfs-calendar-icon" alt="" onclick="qfsOpenDatePicker('qfsApiReturnDate')">
                        </div>
                    </div>
                </div>
                <button type="button" class="btn btn-primary qfs-search-btn" id="qfsSearchFlightsBtn">Search</button>
            </div>
        </div>
    </div>
</div>

<!-- Flight search results modal -->
<div class="modal fade qfs-flight-search" id="qfsFlightsModal" tabindex="-1" role="dialog" aria-labelledby="qfsFlightsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document" style="max-width: 900px;">
        <div class="modal-content">
            <div class="modal-header text-center d-block position-relative" style="border-bottom: 2px solid #f4f6f9;">
                <h5 class="modal-title w-100" id="qfsFlightsModalTitle" style="font-size:1.1rem; color:#555;">Flights</h5>
                <button type="button" class="close position-absolute" data-dismiss="modal" aria-label="Close" style="right: 15px; top: 15px;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="background-color: #f4f6f9; max-height: 70vh; overflow-y: auto; padding: 20px;">
                <div id="qfsFlightsModalBody"></div>
            </div>
            <div class="modal-footer" style="background-color: #fff; border-top: 1px solid #eee;">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
