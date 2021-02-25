import Record from './models/record';
import { getLocalStorage, setLocalStorage } from './lib/localStorageAccess';
import _ from './lodash.custom';
import { makeJSONRequest } from './lib/xhr';


export const options = window.geoip_detect?.options || {
    ajaxurl: "/wp-admin/admin-ajax.php",
    default_locales: ['en'],
    cookie_duration_in_days: 7,
    cookie_name: 'geoip-detect-result'
};

let ajaxPromise = null;

function get_info_raw() {
    if (!ajaxPromise) {
        // Do Ajax Request only once per page load
        const url = options.ajaxurl + '?action=geoip_detect2_get_info_from_current_ip'

        ajaxPromise = makeJSONRequest(url);
    }

    return ajaxPromise;
}

async function get_info_cached() {
    let response = false;
    let storedResponse = false;

    // 1) Load Info from localstorage cookie cache, if possible
    if (options.cookie_name) {
        storedResponse = getLocalStorage(options.cookie_name)
        if (storedResponse && storedResponse.extra) {
            return storedResponse;
        }
    }

    // 2) Get response
    try {
        response = await get_info_raw();
    } catch (err) {
        response = err.responseJSON || err;
    }

    // 3) Save info to localstorage cookie cache
    if (options.cookie_name) {
        storedResponse = getLocalStorage(options.cookie_name)
        if (storedResponse?.extra?.override === true) {
            return storedResponse;
        }

        // This might be an error object - cache it anyway
        setLocalStorage(options.cookie_name, response, options.cookie_duration_in_days * 24 * 60 * 60);
    }

    return response;
}


// These functions allow to override the geodetected data manually (e.g. a country selector)

export function set_override(record, duration_in_days) {
    if (record && typeof(record.serialize) === 'function') {
        record = record.serialize();
    }
    return set_override_data(record, duration_in_days);
}
export function set_override_data(data, duration_in_days) {
    duration_in_days = duration_in_days || options.cookie_duration_in_days;
    if (duration_in_days < 0) {
        console.warn('set_override_data() did nothing: A negative duration doesn\'t make sense. If you want to remove the override, use remove_override() instead.');
        return false;
    }

    if (!data) {
        data = {};
    }
    if (!data.extra) {
        data.extra = {};
    }
    data.extra.override = true;

    setLocalStorage(options.cookie_name, data, duration_in_days * 24 * 60 * 60);
    return true;
}
export function remove_override() {
    setLocalStorage(options.cookie_name, {}, -1);
    return true;
}



export async function get_info() {
    let response = await get_info_cached();

    if (typeof (response) !== 'object') {
        console.error('Geoip-detect: Record should be an object, not a ' + typeof (response), response);
        response = { 'extra': { 'error': response || 'Network error, look at the original server response ...' } };
    }

    const record = new Record(response, options.default_locales);
    return record;
}