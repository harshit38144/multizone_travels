<?php

/**
 * Selectable fields for customer intake links (excludes assign_to, lead_source, referred_by).
 */
function crmLeadIntakeFieldCatalog()
{
    return [
        'contact' => [
            'label' => 'Contact',
            'fields' => [
                'customer_name' => 'Customer Name',
                'customer_phone' => 'Phone',
                'customer_email' => 'Email',
            ],
        ],
        'services' => [
            'label' => 'Services',
            'fields' => [
                'services' => 'Services Required',
            ],
        ],
        'tour_package' => [
            'label' => 'Tour Package',
            'fields' => [
                'tp_travel_date' => 'Preferred Travel Date',
                'tp_departure' => 'Departure City',
                'tp_arrival' => 'Arrival City',
                'tp_tour_type' => 'Tour Type',
                'tp_destination' => 'Destinations',
                'tp_budget' => 'Approx. Budget',
                'tp_hotel_category' => 'Preferred Hotel Category',
                'tp_rooms' => 'Rooms',
                'tp_adults' => 'Adults',
                'tp_children' => 'Children',
                'tp_children_ages' => 'Children Ages',
                'tp_notes' => 'Package Notes',
            ],
        ],
        'cruise' => [
            'label' => 'Cruise',
            'fields' => [
                'cruise_embark_date' => 'Embarkation Date',
                'cruise_line' => 'Cruise Line / Route',
                'cruise_cabin' => 'Cabin Type',
                'cruise_pax' => 'Passengers',
                'cruise_port' => 'Port / City',
            ],
        ],
        'visa' => [
            'label' => 'Visa',
            'fields' => [
                'visa_country' => 'Country',
                'visa_type' => 'Visa Type',
                'visa_travel_date' => 'Travel Date',
                'visa_passport_no' => 'Passport Number',
                'visa_passport_exp' => 'Passport Expiry',
            ],
        ],
        'passport' => [
            'label' => 'Passport',
            'fields' => [
                'passport_service' => 'Service',
                'passport_urgency' => 'Urgency',
                'passport_expiry' => 'Passport Expiry',
                'passport_notes' => 'Notes',
            ],
        ],
        'forex' => [
            'label' => 'Forex',
            'fields' => [
                'forex_currency' => 'Currency',
                'forex_amount' => 'Amount',
                'forex_date' => 'Required Date',
                'forex_product' => 'Product',
                'forex_city' => 'City',
            ],
        ],
    ];
}

function crmLeadIntakeAllFieldKeys()
{
    $keys = [];
    foreach (crmLeadIntakeFieldCatalog() as $group) {
        foreach ($group['fields'] as $key => $label) {
            $keys[] = $key;
        }
    }
    return $keys;
}

/**
 * Fields included when sending a customer form link (contact + all Tour Package fields).
 * Vehicle Type is intentionally omitted from customer links.
 */
function crmLeadIntakeSendLinkDefaultFields()
{
    $exclude = ['tp_departure', 'tp_arrival', 'vehicle_type'];
    $fields = ['customer_name', 'customer_phone', 'customer_email'];
    $catalog = crmLeadIntakeFieldCatalog();
    if (isset($catalog['tour_package']['fields']) && is_array($catalog['tour_package']['fields'])) {
        foreach (array_keys($catalog['tour_package']['fields']) as $key) {
            if (!in_array($key, $exclude, true)) {
                $fields[] = $key;
            }
        }
    }
    return $fields;
}

function crmLeadIntakeNormalizeFields($raw)
{
    $allowed = array_flip(crmLeadIntakeAllFieldKeys());
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    foreach ($raw as $key) {
        $key = trim((string) $key);
        if ($key !== '' && isset($allowed[$key])) {
            $out[$key] = true;
        }
    }
    return array_keys($out);
}

function crmLeadIntakeFieldEnabled(array $enabled, $key)
{
    return in_array($key, $enabled, true);
}

function crmPayloadFieldHasValue($value)
{
    if ($value === null || $value === '') {
        return false;
    }
    if (is_array($value)) {
        foreach ($value as $item) {
            if (crmPayloadFieldHasValue($item)) {
                return true;
            }
        }
        return false;
    }
    return true;
}

/**
 * Infer selected services from intake/customer payload when services[] was not posted.
 */
function crmInferPayloadServices(array $payload)
{
    $services = $payload['services'] ?? [];
    if (!is_array($services)) {
        $services = [];
    }
    $services = array_values(array_filter(array_map('trim', $services), function ($s) {
        return $s !== '';
    }));
    if (count($services) > 0) {
        return array_values(array_unique($services));
    }

    foreach (crmLeadIntakeServiceFieldMap() as $svc => $keys) {
        foreach ($keys as $key) {
            if (isset($payload[$key]) && crmPayloadFieldHasValue($payload[$key])) {
                $services[] = $svc;
                break;
            }
        }
    }

    $services = array_values(array_unique($services));
    return count($services) > 0 ? $services : ['tour_package'];
}

function crmLeadIntakeServiceFieldMap()
{
    return [
        'tour_package' => ['tp_travel_date', 'tp_departure', 'tp_arrival', 'tp_tour_type', 'tp_destination', 'tp_budget', 'tp_hotel_category', 'tp_rooms', 'tp_adults', 'tp_children', 'tp_children_ages', 'tp_notes'],
        'cruise' => ['cruise_embark_date', 'cruise_line', 'cruise_cabin', 'cruise_pax', 'cruise_port'],
        'visa' => ['visa_country', 'visa_type', 'visa_travel_date', 'visa_passport_no', 'visa_passport_exp'],
        'passport' => ['passport_service', 'passport_urgency', 'passport_expiry', 'passport_notes'],
        'forex' => ['forex_currency', 'forex_amount', 'forex_date', 'forex_product', 'forex_city'],
    ];
}

function crmLeadIntakeServiceHasEnabledFields(array $enabled, $serviceKey)
{
    $map = crmLeadIntakeServiceFieldMap();
    $fields = $map[$serviceKey] ?? [];
    foreach ($fields as $field) {
        if (crmLeadIntakeFieldEnabled($enabled, $field)) {
            return true;
        }
    }
    return false;
}

function crmLeadIntakeShowServicesPicker(array $enabled)
{
    return crmLeadIntakeFieldEnabled($enabled, 'services');
}

function crmLeadIntakeAutoServiceValues(array $enabled)
{
    if (crmLeadIntakeFieldEnabled($enabled, 'services')) {
        return [];
    }
    $services = [];
    foreach (array_keys(crmLeadIntakeServiceFieldMap()) as $svc) {
        if (crmLeadIntakeServiceHasEnabledFields($enabled, $svc)) {
            $services[] = $svc;
        }
    }
    return $services;
}

function crmLeadIntakeShowContactSection(array $enabled)
{
    return crmLeadIntakeFieldEnabled($enabled, 'customer_name')
        || crmLeadIntakeFieldEnabled($enabled, 'customer_phone')
        || crmLeadIntakeFieldEnabled($enabled, 'customer_email');
}

function crmLeadIntakeShowTravelSection(array $enabled)
{
    if (crmLeadIntakeShowServicesPicker($enabled)) {
        return true;
    }
    foreach (array_keys(crmLeadIntakeServiceFieldMap()) as $svc) {
        if (crmLeadIntakeServiceHasEnabledFields($enabled, $svc)) {
            return true;
        }
    }
    return false;
}

function crmLeadIntakeHasAnyTravelDetailField(array $enabled)
{
    foreach (crmLeadIntakeServiceFieldMap() as $fields) {
        foreach ($fields as $field) {
            if (crmLeadIntakeFieldEnabled($enabled, $field)) {
                return true;
            }
        }
    }
    return false;
}

function crmLeadIntakeShowServiceCheckbox(array $enabled, $serviceKey)
{
    if (crmLeadIntakeServiceHasEnabledFields($enabled, $serviceKey)) {
        return true;
    }
    if (crmLeadIntakeFieldEnabled($enabled, 'services') && !crmLeadIntakeHasAnyTravelDetailField($enabled)) {
        return true;
    }
    return false;
}

function crmLeadIntakeShowTourRgRow(array $enabled)
{
    return crmLeadIntakeFieldEnabled($enabled, 'tp_budget')
        || crmLeadIntakeFieldEnabled($enabled, 'tp_hotel_category')
        || crmLeadIntakeFieldEnabled($enabled, 'tp_rooms')
        || crmLeadIntakeFieldEnabled($enabled, 'tp_adults')
        || crmLeadIntakeFieldEnabled($enabled, 'tp_children')
        || crmLeadIntakeFieldEnabled($enabled, 'tp_children_ages');
}
