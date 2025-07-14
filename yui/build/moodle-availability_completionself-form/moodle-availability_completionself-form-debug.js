YUI.add('moodle-availability_completionself-form', function (Y, NAME) {

/**
 * JavaScript for form editing completion conditions.
 *
 * @module moodle-availability_completion-form
 */
M.availability_completionself = M.availability_completionself || {};

/**
 * @class M.availability_completionself.form
 * @extends M.core_availability.plugin
 */
M.availability_completionself.form = Y.Object(M.core_availability.plugin);

/**
 * Initialises this plugin.
 *
 * @method initInner
 * @param {Array} cms Array of objects containing cmid => name
 */
M.availability_completionself.form.initInner = function(cms) {
    this.cms = cms;
};

M.availability_completionself.form.getNode = function(json) {
    // Create HTML structure.
    var html = '<span class="col-form-label pr-3"> ' + M.util.get_string('title', 'availability_completionself') + '</span>' +
               ' <span class="availability-group form-group"><label><span class="accesshide">' +
                M.util.get_string('label_completion', 'availability_completionself') +
            ' </span><select class="custom-select" ' +
                            'name="e" title="' + M.util.get_string('label_completion', 'availability_completionself') + '">' +
            '<option value="1">' + M.util.get_string('option_complete', 'availability_completionself') + '</option>' +
            '<option value="0">' + M.util.get_string('option_incomplete', 'availability_completionself') + '</option>' +
            '<option value="2">' + M.util.get_string('option_pass', 'availability_completionself') + '</option>' +
            '<option value="3">' + M.util.get_string('option_fail', 'availability_completionself') + '</option>' +
            '</select></label></span>';
    var node = Y.Node.create('<span class="form-inline">' + html + '</span>');

    // Set initial values.
    if (json.cm !== undefined &&
            node.one('select[name=cm] > option[value=' + json.cm + ']')) {
        node.one('select[name=cm]').set('value', '' + json.cm);
    }
    if (json.e !== undefined) {
        node.one('select[name=e]').set('value', '' + json.e);
    }

    // Add event handlers (first time only).
    if (!M.availability_completionself.form.addedEvents) {
        M.availability_completionself.form.addedEvents = true;
        var root = Y.one('.availability-field');
        root.delegate('change', function() {
            // Whichever dropdown changed, just update the form.
            M.core_availability.form.update();
        }, '.availability_completion select');
    }

    return node;
};

M.availability_completionself.form.fillValue = function(value, node) {
    value.e = parseInt(node.one('select[name=e]').get('value'), 10);
};

M.availability_completionself.form.fillErrors = function(errors, node) {
    var e = parseInt(node.one('select[name=e]').get('value'), 10);
    if (((e === 2) || (e === 3))) {
        // This condition applies to the module itself, so we don't need to check for grade item.
    }
};


}, '@VERSION@', {"requires": ["base", "node", "event", "moodle-core_availability-form"]});
