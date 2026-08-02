<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/quotation_db.php';
require_once __DIR__ . '/../includes/lead_db.php';
require_once __DIR__ . '/../includes/hotel_db.php';

header('Content-Type: application/json; charset=utf-8');

function quotationJson($success, $message, $extra = [])
{
    echo json_encode(array_merge(['success' => (bool) $success, 'message' => (string) $message], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    quotationJson(false, 'Invalid request method.');
}

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
    quotationJson(false, 'Database connection failed.');
}

try {
    crmEnsureQuotationTables($conn);

    $saveMode = ($_POST['save_mode'] ?? 'publish') === 'draft' ? 'draft' : 'publish';
    $isDraftSave = $saveMode === 'draft';
    $wizardStep = max(1, min(6, (int) ($_POST['wizard_step'] ?? 1)));

    $guestName = trim($_POST['guest_name'] ?? '');
    if (!$isDraftSave && $guestName === '') {
        quotationJson(false, 'Guest name is required.');
    }

    $id = (int) ($_POST['id'] ?? 0);

    $referenceName = trim($_POST['reference_name'] ?? '');
    $mobileNo = trim($_POST['mobile_no'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $destination = trim($_POST['destination'] ?? '');
    $tentativeDate = trim($_POST['tentative_date'] ?? '');
    $tentativeDate = ($tentativeDate !== '') ? $tentativeDate : null;
    $nights = max(0, (int) ($_POST['no_of_nights'] ?? 0));
    $adults = max(1, (int) ($_POST['no_of_adults'] ?? 1));
    $children = max(0, (int) ($_POST['no_of_children'] ?? 0));

    function quotationNormalizeJson($raw, $fallback)
    {
        if (is_array($raw)) {
            $encoded = json_encode($raw, JSON_UNESCAPED_UNICODE);
            return ($encoded === false || $encoded === null) ? $fallback : $encoded;
        }
        if (!is_string($raw) || $raw === '') {
            return $fallback;
        }
        $decoded = json_decode($raw, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            return $fallback;
        }
        $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE);
        return ($encoded === false || $encoded === null) ? $fallback : $encoded;
    }

    $flightsJson = quotationNormalizeJson($_POST['flights_json'] ?? '', '[]');
    $hotelsJson = quotationNormalizeJson($_POST['hotels_json'] ?? '', '[]');
    $destinationName = trim((string) ($_POST['destination'] ?? ''));
    $hotelsDecoded = json_decode($hotelsJson, true);
    if (is_array($hotelsDecoded)) {
        // Support legacy flat hotel arrays and new category options shape.
        if (isset($hotelsDecoded['categories']) && is_array($hotelsDecoded['categories'])) {
            foreach ($hotelsDecoded['categories'] as &$hotelCategory) {
                if (!is_array($hotelCategory)) {
                    continue;
                }
                $catHotels = $hotelCategory['hotels'] ?? [];
                if (!is_array($catHotels)) {
                    $catHotels = [];
                }
                $hotelCategory['hotels'] = crmSyncManualQuotationHotelsToMaster($conn, $catHotels, $destinationName);
            }
            unset($hotelCategory);
            $hotelsJson = json_encode($hotelsDecoded, JSON_UNESCAPED_UNICODE);
        } else {
            $hotelsDecoded = crmSyncManualQuotationHotelsToMaster($conn, $hotelsDecoded, $destinationName);
            $hotelsJson = json_encode($hotelsDecoded, JSON_UNESCAPED_UNICODE);
        }
    }
    $itineraryJson = quotationNormalizeJson($_POST['itinerary_json'] ?? '', '[]');
    $costSheetJson = quotationNormalizeJson($_POST['cost_sheet_json'] ?? '', '{}');

    $inclusion = (string) ($_POST['inclusion'] ?? '');
    $exclusion = (string) ($_POST['exclusion'] ?? '');
    $paymentPolicy = (string) ($_POST['payment_policy'] ?? '');
    $cancellationPolicy = (string) ($_POST['cancellation_policy'] ?? '');
    $termsConditions = (string) ($_POST['terms_conditions'] ?? '');
    $otherDetails = (string) ($_POST['other_details'] ?? '');

    $totalCost = (float) ($_POST['total_cost'] ?? 0);
    $profitType = ($_POST['profit_type'] ?? 'percent') === 'amount' ? 'amount' : 'percent';
    $profitValue = (float) ($_POST['profit_value'] ?? 0);
    $packageTotal = (float) ($_POST['package_total'] ?? 0);
    $pricePerAdult = (float) ($_POST['price_per_adult'] ?? 0);
    $quotationTotal = (float) ($_POST['quotation_total'] ?? 0);
    $withoutItinerary = !empty($_POST['without_itinerary']) ? 1 : 0;
    $hideGstNote = !empty($_POST['hide_gst_note']) ? 1 : 0;

    $createdById = (int) ($_SESSION['id'] ?? 0);
    $createdByName = isset($_SESSION['name']) ? trim((string) $_SESSION['name']) : '';
    if ($createdByName === '') {
        $createdByName = null;
    }

    $leadIdPost = max(0, (int) ($_POST['lead_id'] ?? 0));
    $editFromVersion = max(0, (int) ($_POST['edit_from_version'] ?? 0));

    $newStatus = $isDraftSave ? 'draft' : 'published';

    if ($id > 0) {
        $currentVersionBeforeSave = 1;
        $currentStatus = 'published';
        $verCheck = $conn->prepare('SELECT `version`, `status` FROM `crm_quotations` WHERE `id` = ? LIMIT 1');
        if ($verCheck) {
            $verCheck->bind_param('i', $id);
            if ($verCheck->execute()) {
                $verRes = $verCheck->get_result();
                $verRow = $verRes ? $verRes->fetch_assoc() : null;
                $currentVersionBeforeSave = max(1, (int) ($verRow['version'] ?? 1));
                $currentStatus = (string) ($verRow['status'] ?? 'published');
            }
            $verCheck->close();
        }

        if (!$isDraftSave && $editFromVersion > 0 && $editFromVersion >= $currentVersionBeforeSave) {
            quotationJson(false, 'Please open the current quotation version to save changes.');
        }

        $wasDraft = $currentStatus === 'draft';
        $shouldArchive = !$isDraftSave && !$wasDraft;

        if ($shouldArchive) {
            crmQuotationArchiveCurrentVersion($conn, $id);
        }

        $versionSql = $shouldArchive ? ', `version` = `version` + 1' : '';

        $sql = "UPDATE `crm_quotations` SET
            `guest_name`=?, `reference_name`=?, `mobile_no`=?, `email`=?, `destination`=?, `tentative_date`=?,
            `no_of_nights`=?, `no_of_adults`=?, `no_of_children`=?,
            `flights_json`=?, `hotels_json`=?, `itinerary_json`=?,
            `inclusion`=?, `exclusion`=?, `payment_policy`=?, `cancellation_policy`=?, `terms_conditions`=?, `other_details`=?,
            `cost_sheet_json`=?, `total_cost`=?, `profit_type`=?, `profit_value`=?, `package_total`=?,
            `price_per_adult`=?, `quotation_total`=?, `without_itinerary`=?, `hide_gst_note`=?,
            `status`=?, `wizard_step`=?" . $versionSql . "
            WHERE `id`=?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            quotationJson(false, 'Could not prepare update. ' . $conn->error);
        }
        $stmt->bind_param(
            'ssssssiiissssssssssdsddddiiisi',
            $guestName,
            $referenceName,
            $mobileNo,
            $email,
            $destination,
            $tentativeDate,
            $nights,
            $adults,
            $children,
            $flightsJson,
            $hotelsJson,
            $itineraryJson,
            $inclusion,
            $exclusion,
            $paymentPolicy,
            $cancellationPolicy,
            $termsConditions,
            $otherDetails,
            $costSheetJson,
            $totalCost,
            $profitType,
            $profitValue,
            $packageTotal,
            $pricePerAdult,
            $quotationTotal,
            $withoutItinerary,
            $hideGstNote,
            $newStatus,
            $wizardStep,
            $id
        );
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            quotationJson(false, 'Could not update quotation. ' . $err);
        }
        $stmt->close();

        if ($leadIdPost > 0) {
            $linkStmt = $conn->prepare('UPDATE `crm_quotations` SET `lead_id` = ? WHERE `id` = ? AND (`lead_id` IS NULL OR `lead_id` = 0)');
            if ($linkStmt) {
                $linkStmt->bind_param('ii', $leadIdPost, $id);
                $linkStmt->execute();
                $linkStmt->close();
            }
            crmQuotationSyncUidFromLead($conn, $id, $leadIdPost);
        }

        $uid = '';
        $version = 1;
        $uidStmt = $conn->prepare('SELECT `quotation_uid`, `version` FROM `crm_quotations` WHERE `id` = ? LIMIT 1');
        if ($uidStmt) {
            $uidStmt->bind_param('i', $id);
            if ($uidStmt->execute()) {
                $uidRes = $uidStmt->get_result();
                $uidRow = $uidRes ? $uidRes->fetch_assoc() : null;
                $uid = $uidRow ? (string) $uidRow['quotation_uid'] : '';
                $version = $uidRow ? max(1, (int) ($uidRow['version'] ?? 1)) : 1;
            }
            $uidStmt->close();
        }

        if ($leadIdPost > 0) {
            crmLeadSyncFeatureStageForLead($conn, $leadIdPost);
        }

        if ($isDraftSave) {
            quotationJson(true, 'Draft saved. You can continue later from the Quotations list.', [
                'id' => $id,
                'quotation_uid' => $uid,
                'status' => 'draft',
                'wizard_step' => $wizardStep,
            ]);
        }

        $successMessage = 'Quotation saved successfully.';
        if ($wasDraft) {
            $successMessage = 'Draft published successfully.';
        } elseif ($editFromVersion > 0 && $editFromVersion < $currentVersionBeforeSave) {
            $successMessage = 'New version v' . $version . ' created from past version v' . $editFromVersion . '.';
        } elseif ($version > 1) {
            $successMessage = 'Quotation updated to version v' . $version . '.';
        }

        quotationJson(true, $successMessage, [
            'id' => $id,
            'quotation_uid' => $uid,
            'version' => $version ?? 1,
            'status' => 'published',
            'edited_from_version' => $editFromVersion > 0 ? $editFromVersion : null,
        ]);
    }

    if ($leadIdPost <= 0 && ($mobileNo !== '' || $email !== '')) {
        $leadIdPost = crmQuotationResolveLeadId($conn, [
            'lead_id' => 0,
            'mobile_no' => $mobileNo,
            'email' => $email,
        ]);
    }

    if ($leadIdPost > 0) {
        $leadUid = crmQuotationLeadUid($conn, $leadIdPost);
        if ($leadUid !== '' && !crmQuotationUidAvailable($conn, $leadUid, 0)) {
            quotationJson(false, 'A quotation already exists for this lead (' . $leadUid . ').');
        }
    }

    $uid = crmResolveQuotationUid($conn, $leadIdPost);

    $sql = "INSERT INTO `crm_quotations`
        (`quotation_uid`, `lead_id`, `version`, `status`, `wizard_step`, `guest_name`, `reference_name`, `mobile_no`, `email`, `destination`, `tentative_date`,
         `no_of_nights`, `no_of_adults`, `no_of_children`,
         `flights_json`, `hotels_json`, `itinerary_json`,
         `inclusion`, `exclusion`, `payment_policy`, `cancellation_policy`, `terms_conditions`, `other_details`,
         `cost_sheet_json`, `total_cost`, `profit_type`, `profit_value`, `package_total`,
         `price_per_adult`, `quotation_total`, `without_itinerary`, `hide_gst_note`,
         `created_by_id`, `created_by_name`)
        VALUES (?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        quotationJson(false, 'Could not prepare save. ' . $conn->error);
    }
    $leadIdInsert = $leadIdPost > 0 ? $leadIdPost : null;
    $stmt->bind_param(
        'sisissssssiiissssssssssdsddddiiis',
        $uid,
        $leadIdInsert,
        $newStatus,
        $wizardStep,
        $guestName,
        $referenceName,
        $mobileNo,
        $email,
        $destination,
        $tentativeDate,
        $nights,
        $adults,
        $children,
        $flightsJson,
        $hotelsJson,
        $itineraryJson,
        $inclusion,
        $exclusion,
        $paymentPolicy,
        $cancellationPolicy,
        $termsConditions,
        $otherDetails,
        $costSheetJson,
        $totalCost,
        $profitType,
        $profitValue,
        $packageTotal,
        $pricePerAdult,
        $quotationTotal,
        $withoutItinerary,
        $hideGstNote,
        $createdById,
        $createdByName
    );

    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        quotationJson(false, 'Could not save quotation. ' . $err);
    }
    $newId = (int) $stmt->insert_id;
    $stmt->close();

    if ($leadIdPost > 0) {
        crmLeadSyncFeatureStageForLead($conn, $leadIdPost);
    }

    if ($isDraftSave) {
        quotationJson(true, 'Draft saved. You can continue later from the Quotations list.', [
            'id' => $newId,
            'quotation_uid' => $uid,
            'status' => 'draft',
            'wizard_step' => $wizardStep,
        ]);
    }

    quotationJson(true, 'Quotation saved successfully.', [
        'id' => $newId,
        'quotation_uid' => $uid,
        'status' => 'published',
    ]);
} catch (Throwable $e) {
    quotationJson(false, 'Save failed: ' . $e->getMessage());
}
