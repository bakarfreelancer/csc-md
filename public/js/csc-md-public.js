/**
 * CSC Members Portal — Public JS
 * Handles: login form, join/registration form, org typeahead, step navigation.
 */
(function ($) {
    'use strict';

    if (typeof cscAjax === 'undefined') return;

    /* -----------------------------------------------------------------------
     * Utility helpers
     * --------------------------------------------------------------------- */
    function showAlert($el, message, type) {
        $el.removeClass('csc-error csc-success csc-info')
           .addClass('csc-alert csc-' + type)
           .text(message)
           .show();
    }

    function clearAlert($el) {
        $el.hide().text('');
    }

    /* -----------------------------------------------------------------------
     * LOGIN FORM
     * --------------------------------------------------------------------- */
    var $loginForm = $('#csc-login-form');

    $loginForm.on('submit', function (e) {
        e.preventDefault();

        var $btn = $loginForm.find('.csc-btn-primary');
        var $msg = $('#csc-login-message');

        clearAlert($msg);
        $btn.prop('disabled', true).text('Logging in\u2026');

        $.ajax({
            url:  cscAjax.ajaxUrl,
            type: 'POST',
            data: {
                action:     'csc_login',
                nonce:      cscAjax.loginNonce,
                email:      $loginForm.find('[name="email"]').val().trim(),
                password:   $loginForm.find('[name="password"]').val(),
                remember_me: $loginForm.find('[name="remember_me"]').is(':checked') ? 1 : 0,
            },
            success: function (res) {
                if (res.success) {
                    if (res.data.require_2fa) {
                        // Show 2FA panel
                        $('#csc-2fa-token').val(res.data.token);
                        $('#csc-2fa-nonce').val(res.data.nonce);
                        $loginForm.hide();
                        $('#csc-2fa-panel').show();
                        $('#csc-2fa-code').focus();
                    } else {
                        window.location.href = res.data.redirect;
                    }
                } else {
                    showAlert($msg, res.data.message, 'error');
                }
                $btn.prop('disabled', false).text('Login');
            },
            error: function () {
                showAlert($msg, 'A network error occurred. Please try again.', 'error');
                $btn.prop('disabled', false).text('Login');
            },
        });
    });

    /* -----------------------------------------------------------------------
     * TWO-FACTOR AUTHENTICATION
     * --------------------------------------------------------------------- */

    // Verify 2FA code
    $('#csc-2fa-form').on('submit', function (e) {
        e.preventDefault();

        var $btn = $(this).find('.csc-btn-primary');
        var $msg = $('#csc-2fa-message');

        clearAlert($msg);
        $btn.prop('disabled', true).text('Verifying\u2026');

        $.ajax({
            url:  cscAjax.ajaxUrl,
            type: 'POST',
            data: {
                action: 'csc_verify_2fa',
                nonce:  $('#csc-2fa-nonce').val(),
                token:  $('#csc-2fa-token').val(),
                code:   $('#csc-2fa-code').val().trim(),
            },
            success: function (res) {
                if (res.success) {
                    window.location.href = res.data.redirect;
                } else {
                    showAlert($msg, res.data.message, 'error');
                    $btn.prop('disabled', false).text('Verify & Sign In');
                }
            },
            error: function () {
                showAlert($msg, 'A network error occurred. Please try again.', 'error');
                $btn.prop('disabled', false).text('Verify & Sign In');
            },
        });
    });

    // Allow only digits in code input
    $('#csc-2fa-code').on('input', function () {
        $(this).val($(this).val().replace(/\D/g, ''));
    });

    // Resend code
    $('#csc-2fa-resend').on('click', function () {
        var $btn = $(this);
        var $msg = $('#csc-2fa-message');

        $btn.prop('disabled', true).text('Sending\u2026');
        clearAlert($msg);

        $.ajax({
            url:  cscAjax.ajaxUrl,
            type: 'POST',
            data: {
                action: 'csc_resend_2fa',
                nonce:  $('#csc-2fa-nonce').val(),
                token:  $('#csc-2fa-token').val(),
            },
            success: function (res) {
                if (res.success) {
                    showAlert($msg, res.data.message, 'success');
                } else {
                    showAlert($msg, res.data.message, 'error');
                }
            },
            error: function () {
                showAlert($msg, 'Network error. Please try again.', 'error');
            },
            complete: function () {
                $btn.prop('disabled', false).text('Resend code');
            },
        });
    });

    // Back to login
    $('#csc-2fa-back').on('click', function () {
        $('#csc-2fa-panel').hide();
        $('#csc-2fa-code').val('');
        clearAlert($('#csc-2fa-message'));
        $loginForm.show();
    });

    /* -----------------------------------------------------------------------
     * FORGOT PASSWORD FORM
     * --------------------------------------------------------------------- */
    $('#csc-forgot-form').on('submit', function (e) {
        e.preventDefault();

        var $form = $(this);
        var $btn  = $form.find('.csc-btn-primary');
        var $msg  = $('#csc-forgot-message');

        clearAlert($msg);
        $btn.prop('disabled', true).text('Sending\u2026');

        $.ajax({
            url:  cscAjax.ajaxUrl,
            type: 'POST',
            data: {
                action: 'csc_forgot_password',
                nonce:  $form.data('nonce'),
                email:  $form.find('[name="email"]').val().trim(),
            },
            success: function (res) {
                if (res.success) {
                    showAlert($msg, res.data.message, 'success');
                    $form.find('[name="email"]').val('');
                } else {
                    showAlert($msg, res.data.message, 'error');
                }
            },
            error: function () {
                showAlert($msg, 'A network error occurred. Please try again.', 'error');
            },
            complete: function () {
                $btn.prop('disabled', false).text('Send Reset Link');
            },
        });
    });

    /* -----------------------------------------------------------------------
     * SET PASSWORD FORM
     * --------------------------------------------------------------------- */

    // Password strength meter for set-password page
    $('#csc-setpw-new').on('input', function () {
        var pw   = $(this).val();
        var $bar = $('#csc-setpw-strength .csc-pw-strength-bar');
        var $lbl = $('#csc-setpw-strength-label');

        if (!pw) {
            $bar.removeClass('strength-1 strength-2 strength-3 strength-4');
            $lbl.text('');
            return;
        }

        var score = 0;
        if (pw.length >= 8)           score++;
        if (/[A-Z]/.test(pw))         score++;
        if (/[0-9]/.test(pw))         score++;
        if (/[^A-Za-z0-9]/.test(pw))  score++;

        var labels = ['', 'Weak', 'Fair', 'Good', 'Strong'];
        $bar.removeClass('strength-1 strength-2 strength-3 strength-4')
            .addClass('strength-' + score);
        $lbl.text(labels[score] || '');
    });

    $('#csc-setpw-form').on('submit', function (e) {
        e.preventDefault();

        var $form    = $(this);
        var $btn     = $form.find('.csc-btn-primary');
        var $msg     = $('#csc-setpw-message');
        var password = $form.find('[name="new_password"]').val();
        var confirm  = $form.find('[name="confirm_password"]').val();

        clearAlert($msg);

        if (password !== confirm) {
            showAlert($msg, 'Passwords do not match.', 'error');
            return;
        }

        if (password.length < 8) {
            showAlert($msg, 'Password must be at least 8 characters.', 'error');
            return;
        }

        $btn.prop('disabled', true).text('Saving\u2026');

        $.ajax({
            url:  cscAjax.ajaxUrl,
            type: 'POST',
            data: {
                action:           'csc_set_password',
                nonce:            $form.data('nonce'),
                key:              $form.data('key'),
                login:            $form.data('login'),
                new_password:     password,
                confirm_password: confirm,
            },
            success: function (res) {
                if (res.success) {
                    window.location.href = res.data.redirect;
                } else {
                    showAlert($msg, res.data.message, 'error');
                    $btn.prop('disabled', false).text('Set Password');
                }
            },
            error: function () {
                showAlert($msg, 'A network error occurred. Please try again.', 'error');
                $btn.prop('disabled', false).text('Set Password');
            },
        });
    });

    /* -----------------------------------------------------------------------
     * ORGANISATION TYPEAHEAD
     * --------------------------------------------------------------------- */
    var $orgSearch   = $('#csc-org-search');
    var $orgId       = $('#csc-org-id');
    var $orgDropdown = $('#csc-org-dropdown');
    var searchTimer;

    function fetchOrgs(query) {
        $.ajax({
            url:  cscAjax.ajaxUrl,
            type: 'GET',
            data: {
                action: 'csc_search_orgs',
                nonce:  cscAjax.publicNonce,
                q:      query,
            },
            success: function (res) {
                $orgDropdown.empty();
                if (res.success && res.data.length > 0) {
                    $.each(res.data, function (i, org) {
                        $('<li>', {
                            class:       'csc-typeahead-item',
                            text:        org.name,
                            'data-id':   org.id,
                            'data-name': org.name,
                            role:        'option',
                        }).appendTo($orgDropdown);
                    });
                } else {
                    $('<li>', {
                        class: 'csc-typeahead-item csc-typeahead-item--empty',
                        text:  'No organisations found',
                        role:  'option',
                    }).appendTo($orgDropdown);
                }
                $orgDropdown.show();
            },
        });
    }

    // Show all orgs on focus/click
    $orgSearch.on('focus click', function () {
        if ($orgDropdown.is(':hidden')) {
            fetchOrgs($orgSearch.val().trim());
        }
    });

    // Filter on input
    $orgSearch.on('input', function () {
        clearTimeout(searchTimer);
        var q = $(this).val().trim();
        $orgId.val('');
        searchTimer = setTimeout(function () { fetchOrgs(q); }, 280);
    });

    // Select an item
    $(document).on('click', '.csc-typeahead-item:not(.csc-typeahead-item--empty)', function () {
        var id   = $(this).data('id');
        var name = $(this).data('name');
        $orgSearch.val(name);
        $orgId.val(id);
        $orgDropdown.hide();
    });

    // Close dropdown on outside click
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.csc-typeahead-wrap').length) {
            $orgDropdown.hide();
        }
    });

    /* -----------------------------------------------------------------------
     * REGISTRATION — Country / County typeaheads for new org form
     * --------------------------------------------------------------------- */
    if ($('#reg-country-input').length && typeof window.cscCountries !== 'undefined') {

        function setRegCountyVisibility(isUK) {
            if (isUK) {
                $('#reg-county-group').show();
            } else {
                $('#reg-county-group').hide();
                $('#reg-county-input').val('');
                $('#reg-county-hidden').val('');
            }
        }

        makeLocalTypeahead({
            inputId:    '#reg-country-input',
            hiddenId:   '#reg-country-hidden',
            dropdownId: '#reg-country-dropdown',
            getData:    function () { return window.cscCountries; },
            renderItem: function (item) { return { label: item, sub: null }; },
            onSelect:   function (value) { setRegCountyVisibility(value === 'United Kingdom'); },
            onClear:    function () { setRegCountyVisibility(false); },
        });

        makeLocalTypeahead({
            inputId:    '#reg-county-input',
            hiddenId:   '#reg-county-hidden',
            dropdownId: '#reg-county-dropdown',
            getData:    function () { return window.cscUkCounties || []; },
            renderItem: function (item) { return { label: item.name, sub: item.region }; },
            onSelect:   null,
            onClear:    null,
        });

        makeLocalTypeahead({
            inputId:    '#reg-sector-input',
            hiddenId:   '#reg-sector-hidden',
            dropdownId: '#reg-sector-dropdown',
            getData:    function () { return window.cscRegSectors || []; },
            renderItem: function (item) { return { label: item, sub: null }; },
        });

        makeLocalTypeahead({
            inputId:    '#reg-industry-input',
            hiddenId:   '#reg-industry-hidden',
            dropdownId: '#reg-industry-dropdown',
            getData:    function () { return window.cscRegIndustries || []; },
            renderItem: function (item) { return { label: item, sub: null }; },
        });

        makeLocalTypeahead({
            inputId:    '#reg-igp-input',
            hiddenId:   '#reg-igp-hidden',
            dropdownId: '#reg-igp-dropdown',
            getData:    function () { return window.cscRegIgp || []; },
            renderItem: function (item) { return { label: item, sub: null }; },
        });
    }

    /* -----------------------------------------------------------------------
     * "CAN'T FIND? REGISTER A NEW ONE" TOGGLE
     * --------------------------------------------------------------------- */
    $('#csc-register-org-check').on('change', function () {
        var checked = $(this).is(':checked');

        if (checked) {
            $orgSearch.val('').prop('disabled', true);
            $orgId.val('');
            $orgDropdown.hide();
            $('#csc-new-org-section').slideDown(220);
            $('#csc-personal-inline').slideUp(180);
        } else {
            $orgSearch.prop('disabled', false);
            $('#csc-new-org-section').slideUp(180);
            $('#csc-personal-inline').slideDown(220);
        }
    });

    /* -----------------------------------------------------------------------
     * STEP NAVIGATION: Org form Next button (screen 03 -> 04)
     * --------------------------------------------------------------------- */
    $('#csc-next-btn').on('click', function () {
        var orgName = $('#csc-org-name').val().trim();
        var $msg    = $('#csc-join-message');

        if (!orgName) {
            showAlert($msg, 'Please enter your organisation name.', 'error');
            $('#csc-org-name').focus();
            return;
        }

        clearAlert($msg);

        $('#csc-step-1').fadeOut(180, function () {
            $('#csc-step-2').fadeIn(200);
            // Override back button to return to step 1
            $('#csc-back-btn').off('click.cscStep').on('click.cscStep', function (e) {
                e.preventDefault();
                $('#csc-step-2').fadeOut(180, function () {
                    $('#csc-step-1').fadeIn(200);
                });
                $('#csc-back-btn').off('click.cscStep');
            });
        });
    });

    /* -----------------------------------------------------------------------
     * JOIN FORM SUBMIT — existing org flow (step 1 inline personal data)
     * --------------------------------------------------------------------- */
    $('#csc-join-form').on('submit', function (e) {
        e.preventDefault();

        var $form       = $(this);
        var $btn        = $form.find('.csc-btn-primary');
        var $msg        = $('#csc-join-message');
        var orgId       = $orgId.val();
        var registerNew = $('#csc-register-org-check').is(':checked');

        clearAlert($msg);

        if (!registerNew && !orgId) {
            showAlert($msg, "Please select an organisation or tick \u201cCan't find? Register a new one\u201d.", 'error');
            $orgSearch.focus();
            return;
        }

        if (!$form.find('[name="consent_sharing"]').is(':checked') ||
            !$form.find('[name="consent_directory"]').is(':checked')) {
            showAlert($msg, 'Please accept the required consent statements to proceed.', 'error');
            return;
        }

        $btn.prop('disabled', true).text('Submitting\u2026');

        var data = {
            action:            'csc_register',
            nonce:             cscAjax.registerNonce,
            first_name:        $form.find('[name="first_name"]').val().trim(),
            last_name:         $form.find('[name="last_name"]').val().trim(),
            job_title:         $form.find('[name="job_title"]').val().trim(),
            email:             $form.find('[name="email"]').val().trim(),
            linkedin:          $form.find('[name="linkedin"]').val().trim(),
            bio:               $form.find('[name="bio"]').val().trim(),
            organisation_id:   orgId,
            register_new_org:  registerNew ? 1 : 0,
            consent_marketing: $form.find('[name="consent_marketing"]').is(':checked') ? 1 : 0,
            consent_sharing:   $form.find('[name="consent_sharing"]').is(':checked') ? 1 : 0,
            consent_directory: $form.find('[name="consent_directory"]').is(':checked') ? 1 : 0,
        };

        if (registerNew) {
            data.org_name        = $('#csc-org-name').val().trim();
            data.org_address     = $('#csc-org-address').val().trim();
            data.org_city        = $('#csc-org-city').val().trim();
            data.org_country     = $('#reg-country-hidden').val();
            data.org_county      = $('#reg-county-hidden').val();
            data.org_postcode    = $('#csc-org-postcode').val().trim();
            data.org_sector      = $('#reg-sector-hidden').val();
            data.org_industry    = $('#reg-industry-hidden').val();
            data.org_igp         = $('#reg-igp-hidden').val();
            data.org_phone       = $('#csc-org-phone').val().trim();
            data.org_website     = $('#csc-org-website').val().trim();
            data.org_description = $('#csc-org-description').val().trim();
        }

        submitRegistration(data, $btn, $msg, $form);
    });

    /* -----------------------------------------------------------------------
     * PERSONAL FORM SUBMIT — new org flow (step 2)
     * --------------------------------------------------------------------- */
    $('#csc-personal-form').on('submit', function (e) {
        e.preventDefault();

        var $form = $(this);
        var $btn  = $form.find('.csc-btn-primary');
        var $msg  = $('#csc-step2-message');

        clearAlert($msg);

        if (!$form.find('[name="consent_sharing"]').is(':checked') ||
            !$form.find('[name="consent_directory"]').is(':checked')) {
            showAlert($msg, 'Please accept the required consent statements to proceed.', 'error');
            return;
        }

        $btn.prop('disabled', true).text('Submitting\u2026');

        var data = {
            action:            'csc_register',
            nonce:             cscAjax.registerNonce,
            first_name:        $('#csc-first-name-2').val().trim(),
            last_name:         $('#csc-last-name-2').val().trim(),
            job_title:         $('#csc-job-title-2').val().trim(),
            email:             $('#csc-email-2').val().trim(),
            linkedin:          $('#csc-linkedin-2').val().trim(),
            bio:               $('#csc-bio-2').val().trim(),
            organisation_id:   '',
            register_new_org:  1,
            org_name:          $('#csc-org-name').val().trim(),
            org_address:       $('#csc-org-address').val().trim(),
            org_city:          $('#csc-org-city').val().trim(),
            org_country:       $('#reg-country-hidden').val(),
            org_county:        $('#reg-county-hidden').val(),
            org_postcode:      $('#csc-org-postcode').val().trim(),
            org_sector:        $('#reg-sector-hidden').val(),
            org_industry:      $('#reg-industry-hidden').val(),
            org_igp:           $('#reg-igp-hidden').val(),
            org_phone:         $('#csc-org-phone').val().trim(),
            org_website:       $('#csc-org-website').val().trim(),
            org_description:   $('#csc-org-description').val().trim(),
            consent_marketing: $form.find('[name="consent_marketing"]').is(':checked') ? 1 : 0,
            consent_sharing:   $form.find('[name="consent_sharing"]').is(':checked') ? 1 : 0,
            consent_directory: $form.find('[name="consent_directory"]').is(':checked') ? 1 : 0,
        };

        submitRegistration(data, $btn, $msg, $form);
    });

    /* -----------------------------------------------------------------------
     * Shared registration AJAX submission
     * --------------------------------------------------------------------- */
    function submitRegistration(data, $btn, $msg, $form) {
        $.ajax({
            url:  cscAjax.ajaxUrl,
            type: 'POST',
            data: data,
            success: function (res) {
                if (res.success) {
                    // Reset form state
                    $form[0].reset();
                    $('#csc-new-org-section').hide();
                    $('#csc-personal-inline').show();
                    $('#csc-register-org-check').prop('checked', false);
                    $orgSearch.prop('disabled', false).val('');
                    $orgId.val('');
                    $('#csc-step-2').hide();
                    $('#csc-step-1').show();
                    $('#csc-back-btn').off('click.cscStep');
                    // Show confirmation modal
                    $('#csc-modal-overlay').fadeIn(200);
                } else {
                    showAlert($msg, res.data.message, 'error');
                }
                $btn.prop('disabled', false).text('Submit Application');
            },
            error: function () {
                showAlert($msg, 'A network error occurred. Please try again.', 'error');
                $btn.prop('disabled', false).text('Submit Application');
            },
        });
    }

    /* -----------------------------------------------------------------------
     * MODAL — close handlers
     * --------------------------------------------------------------------- */
    $('#csc-modal-close-btn').on('click', function () {
        $('#csc-modal-overlay').fadeOut(180);
    });

    $('#csc-modal-overlay').on('click', function (e) {
        if ($(e.target).is('#csc-modal-overlay')) {
            $(this).fadeOut(180);
        }
    });

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') {
            $('#csc-modal-overlay').fadeOut(180);
            closeSidebar();
        }
    });

    /* -----------------------------------------------------------------------
     * PROFILE & SETTINGS — shared Save Changes handler
     * --------------------------------------------------------------------- */
    function submitPortalForm($form, $msg, $btn) {
        var action = $form.data('action');
        var nonce  = $form.data('nonce');
        if (!action || !nonce) return;

        var formData = $form.serializeArray();
        formData.push({ name: 'action', value: action });
        formData.push({ name: 'nonce',  value: nonce });

        // Company form needs org_id
        var orgId = $form.data('org-id');
        if (orgId) formData.push({ name: 'org_id', value: orgId });

        var origText = $btn.text();
        $btn.prop('disabled', true).text('Saving\u2026');
        clearAlert($msg);

        $.ajax({
            url:  cscAjax.ajaxUrl,
            type: 'POST',
            data: formData,
            success: function (res) {
                if (res.success) {
                    showAlert($msg, res.data.message, 'success');
                } else {
                    showAlert($msg, res.data.message, 'error');
                }
            },
            error: function () {
                showAlert($msg, 'A network error occurred. Please try again.', 'error');
            },
            complete: function () {
                $btn.prop('disabled', false).text(origText);
                $('html, body').animate({ scrollTop: $msg.offset().top - 80 }, 300);
            },
        });
    }

    // Profile page
    $('#csc-profile-save-btn').on('click', function () {
        var $form = $('#csc-profile-form');
        var $msg  = $('#csc-profile-message');
        if ($form.length) submitPortalForm($form, $msg, $(this));
    });

    // Settings page
    $('#csc-settings-save-btn').on('click', function () {
        var $form = $('#csc-settings-form');
        var $msg  = $('#csc-settings-message');
        if ($form.length) submitPortalForm($form, $msg, $(this));
    });

    /* -----------------------------------------------------------------------
     * SKILLS TAG INPUT
     * --------------------------------------------------------------------- */
    var $skillsWrap   = $('#csc-skills-wrap');
    var $skillsInput  = $('#csc-skills-input');
    var $skillsHidden = $('#csc-skills-hidden');

    function getSkills() {
        var val = $skillsHidden.val().trim();
        return val ? val.split(',').map(function (t) { return t.trim(); }).filter(Boolean) : [];
    }

    function updateSkillsHidden() {
        $skillsHidden.val(getSkills().join(', '));
    }

    function addSkill(tag) {
        tag = tag.trim();
        if (!tag) return;
        var skills = getSkills();
        if (skills.indexOf(tag) !== -1) return; // no duplicates
        skills.push(tag);
        $skillsHidden.val(skills.join(', '));

        var $chip = $('<span class="csc-tag-chip">')
            .text(tag)
            .append(
                $('<button type="button" class="csc-tag-chip-remove" aria-label="Remove ' + tag + '">')
                    .text('×')
                    .data('tag', tag)
            );
        $('#csc-skills-chips').append($chip);
    }

    $skillsWrap.on('click', function () { $skillsInput.focus(); });

    $skillsInput.on('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            addSkill($(this).val());
            $(this).val('');
        }
        if (e.key === 'Backspace' && $(this).val() === '') {
            var skills = getSkills();
            if (skills.length) {
                skills.pop();
                $skillsHidden.val(skills.join(', '));
                $('#csc-skills-chips .csc-tag-chip').last().remove();
            }
        }
    });

    $skillsInput.on('blur', function () {
        var val = $(this).val().trim();
        if (val) { addSkill(val); $(this).val(''); }
    });

    $(document).on('click', '.csc-tag-chip-remove', function () {
        var tag    = $(this).data('tag');
        var skills = getSkills().filter(function (t) { return t !== tag; });
        $skillsHidden.val(skills.join(', '));
        $(this).closest('.csc-tag-chip').remove();
    });

    /* -----------------------------------------------------------------------
     * PASSWORD STRENGTH METER
     * --------------------------------------------------------------------- */
    $('#pw-new').on('input', function () {
        var pw  = $(this).val();
        var $bar = $('#csc-pw-strength .csc-pw-strength-bar');
        var $lbl = $('#csc-pw-strength-label');

        if (!pw) {
            $bar.removeClass('strength-1 strength-2 strength-3 strength-4');
            $lbl.text('');
            return;
        }

        var score = 0;
        if (pw.length >= 8)                    score++;
        if (/[A-Z]/.test(pw))                  score++;
        if (/[0-9]/.test(pw))                  score++;
        if (/[^A-Za-z0-9]/.test(pw))           score++;

        var labels = ['', 'Weak', 'Fair', 'Good', 'Strong'];
        $bar.removeClass('strength-1 strength-2 strength-3 strength-4')
            .addClass('strength-' + score);
        $lbl.text(labels[score] || '');
    });

    /* -----------------------------------------------------------------------
     * PASSWORD SHOW / HIDE TOGGLE
     * --------------------------------------------------------------------- */
    $(document).on('click', '.csc-pw-toggle', function () {
        var target = $(this).data('target');
        var $input = $('#' + target);
        var isText = $input.attr('type') === 'text';
        $input.attr('type', isText ? 'password' : 'text');
        $(this).find('.csc-pw-eye').css('opacity', isText ? '1' : '0.5');
    });

    /* -----------------------------------------------------------------------
     * LOCAL TYPEAHEAD — reusable for static lists
     * opts: { inputId, hiddenId, dropdownId, getData, renderItem, onSelect, onClear }
     * getData()  → array of items to filter
     * renderItem(item) → { label: string, sub: string|null }
     * onSelect(item, $input, $hidden) called when user picks an item
     * onClear($input, $hidden) called when input is cleared/changed
     * --------------------------------------------------------------------- */
    function makeLocalTypeahead(opts) {
        var $input    = $(opts.inputId);
        var $hidden   = opts.hiddenId ? $(opts.hiddenId) : $([]);
        var $dropdown = $(opts.dropdownId);
        var MAX_SHOW  = 120;

        if (!$input.length) return;

        function normalise(s) { return s.toLowerCase().replace(/[^a-z0-9 ]/g, ''); }

        function buildDropdown(query) {
            var all     = opts.getData();
            var q       = normalise(query.trim());
            var matches = q
                ? all.filter(function (item) {
                    var r = opts.renderItem(item);
                    return normalise(r.label).indexOf(q) !== -1;
                  })
                : all;

            $dropdown.empty();

            if (!matches.length) {
                $('<li>', { class: 'csc-typeahead-item csc-typeahead-item--empty', text: 'No results' })
                    .appendTo($dropdown);
                $dropdown.show();
                return;
            }

            var shown = 0;
            var lastSub = null;
            $.each(matches, function (i, item) {
                if (shown >= MAX_SHOW) return false;
                var r = opts.renderItem(item);
                // Section header when sub (region) changes
                if (r.sub && r.sub !== lastSub) {
                    $('<li>', { class: 'csc-typeahead-group', text: r.sub, 'aria-hidden': 'true' })
                        .appendTo($dropdown);
                    lastSub = r.sub;
                }
                $('<li>', {
                    class:        'csc-typeahead-item',
                    text:         r.label,
                    'data-value': r.label,
                    role:         'option',
                }).appendTo($dropdown);
                shown++;
            });

            $dropdown.show();
        }

        $input.on('focus click', function () {
            if ($dropdown.is(':hidden')) buildDropdown($input.val());
        });

        $input.on('input', function () {
            var val = $input.val();
            if (!val.trim()) {
                $hidden.val('');
                if (opts.onClear) opts.onClear($input, $hidden);
            }
            buildDropdown(val);
        });

        $dropdown.on('click', '.csc-typeahead-item:not(.csc-typeahead-item--empty)', function () {
            var value = $(this).data('value');
            $input.val(value);
            $hidden.val(value);
            $dropdown.hide();
            if (opts.onSelect) opts.onSelect(value, $input, $hidden);
        });

        // Close on outside click
        $(document).on('click.localTypeahead', function (e) {
            if (!$(e.target).closest($input).length &&
                !$(e.target).closest($dropdown).length) {
                $dropdown.hide();
            }
        });

        // Escape key
        $input.on('keydown', function (e) {
            if (e.key === 'Escape') $dropdown.hide();
        });
    }

    /* -----------------------------------------------------------------------
     * COUNTRY + COUNTY TYPEAHEADS (Company Information tab)
     * --------------------------------------------------------------------- */
    if ($('#co-country-input').length && typeof window.cscCountries !== 'undefined') {

        function setCountyVisibility(isUK) {
            var $row    = $('#csc-city-county-row');
            var $county = $('#csc-county-group');
            if (isUK) {
                $row.css('grid-template-columns', '1fr 1fr');
                $county.show();
            } else {
                $row.css('grid-template-columns', '1fr');
                $county.hide();
                $('#co-county-input').val('');
                $('#co-county-hidden').val('');
            }
        }

        makeLocalTypeahead({
            inputId:    '#co-country-input',
            hiddenId:   '#co-country-hidden',
            dropdownId: '#csc-country-dropdown',
            getData:    function () { return window.cscCountries; },
            renderItem: function (item) { return { label: item, sub: null }; },
            onSelect: function (value) {
                setCountyVisibility(value === 'United Kingdom');
            },
            onClear: function () {
                setCountyVisibility(false);
            },
        });

        // County typeahead — UK postal counties with region grouping
        makeLocalTypeahead({
            inputId:    '#co-county-input',
            hiddenId:   '#co-county-hidden',
            dropdownId: '#csc-county-dropdown',
            getData:    function () { return window.cscUkCounties || []; },
            renderItem: function (item) { return { label: item.name, sub: item.region }; },
            onSelect:   null,
            onClear:    null,
        });
    }

    /* -----------------------------------------------------------------------
     * COPY WEBSITE URL
     * --------------------------------------------------------------------- */
    $('#csc-copy-website').on('click', function () {
        var url = $('#co-website').val().trim();
        if (!url) return;
        navigator.clipboard.writeText(url).then(function () {
            var $btn = $('#csc-copy-website');
            $btn.attr('title', 'Copied!');
            setTimeout(function () { $btn.attr('title', 'Copy URL'); }, 1500);
        });
    });

    /* -----------------------------------------------------------------------
     * AVATAR / LOGO UPLOAD
     * --------------------------------------------------------------------- */
    function handleAvatarUpload(opts) {
        // opts: { btnId, inputId, photoId, initialsId, action }
        var $btn      = $(opts.btnId);
        var $input    = $(opts.inputId);
        var $photo    = $(opts.photoId);
        var $initials = $(opts.initialsId);

        if (!$btn.length) return;

        // Button click → trigger file picker
        $btn.on('click', function () {
            $input.trigger('click');
        });

        // File chosen → instant preview then upload
        $input.on('change', function () {
            var file = this.files[0];
            if (!file) return;

            // Instant local preview
            var reader = new FileReader();
            reader.onload = function (e) {
                $photo.attr('src', e.target.result).show();
                $initials.hide();
            };
            reader.readAsDataURL(file);

            // Build FormData for AJAX upload
            var formData = new FormData();
            formData.append('action', opts.action);
            formData.append('nonce',  $btn.data('nonce'));
            formData.append('photo',  file);

            var origHtml = $btn.html();
            $btn.addClass('is-uploading').prop('disabled', true).text('Uploading\u2026');

            $.ajax({
                url:         cscAjax.ajaxUrl,
                type:        'POST',
                data:        formData,
                processData: false,
                contentType: false,
                success: function (res) {
                    if (res.success) {
                        $photo.attr('src', res.data.url).show();
                        $initials.hide();
                        // Swap button label to "Change image"
                        $btn.html(origHtml.replace(/Add image/, 'Change image'));
                    } else {
                        alert(res.data.message || 'Upload failed. Please try again.');
                        // Revert preview if upload failed
                        if (!$photo.data('confirmed')) {
                            $photo.hide();
                            $initials.show();
                        }
                    }
                },
                error: function () {
                    alert('A network error occurred. Please try again.');
                    $photo.hide();
                    $initials.show();
                },
                complete: function () {
                    $btn.removeClass('is-uploading').prop('disabled', false);
                    if ($btn.text() === 'Uploading\u2026') {
                        $btn.html(origHtml);
                    }
                    // Reset file input so same file can be re-selected
                    $input.val('');
                },
            });
        });
    }

    // Personal photo
    handleAvatarUpload({
        btnId:      '#csc-user-photo-btn',
        inputId:    '#csc-user-photo-input',
        photoId:    '#csc-user-avatar-photo',
        initialsId: '#csc-user-avatar-initials',
        action:     'csc_upload_user_photo',
    });

    // Company logo
    handleAvatarUpload({
        btnId:      '#csc-org-logo-btn',
        inputId:    '#csc-org-logo-input',
        photoId:    '#csc-org-avatar-photo',
        initialsId: '#csc-org-avatar-initials',
        action:     'csc_upload_org_logo',
    });

    /* -----------------------------------------------------------------------
     * SIGN OUT ALL OTHER DEVICES
     * --------------------------------------------------------------------- */
    $('#csc-sign-out-all').on('click', function () {
        var $btn   = $(this);
        var $msg   = $('#csc-settings-message');
        var nonce  = $btn.data('nonce');

        $btn.prop('disabled', true).text('Signing out\u2026');
        clearAlert($msg);

        $.ajax({
            url:  cscAjax.ajaxUrl,
            type: 'POST',
            data: { action: 'csc_sign_out_all_devices', nonce: nonce },
            success: function (res) {
                if (res.success) {
                    showAlert($msg, res.data.message, 'success');
                } else {
                    showAlert($msg, res.data.message, 'error');
                }
            },
            error: function () {
                showAlert($msg, 'Network error. Please try again.', 'error');
            },
            complete: function () {
                $btn.prop('disabled', false).text('Sign out of all other devices');
            },
        });
    });

    /* -----------------------------------------------------------------------
     * MULTI-SELECT widget — typeahead dropdown with removable chips
     * opts: { inputId, hiddenId, dropdownId, pillsId, getData, getLabel, getValue, preSelected }
     * --------------------------------------------------------------------- */
    function makeMultiSelect(opts) {
        var $input    = $(opts.inputId);
        var $hidden   = $(opts.hiddenId);
        var $dropdown = $(opts.dropdownId);
        var $pills    = $(opts.pillsId);

        if (!$input.length) return;

        function getLabel(item) { return opts.getLabel ? opts.getLabel(item) : item; }
        function getValue(item) { return opts.getValue ? opts.getValue(item) : item; }

        function getSelected() {
            var v = $hidden.val().trim();
            return v ? v.split(',').map($.trim).filter(Boolean) : [];
        }

        function setSelected(arr) { $hidden.val(arr.join(',')); }

        function renderPills() {
            $pills.empty();
            getSelected().forEach(function (val) {
                $('<span class="csc-ms-pill">')
                    .text(val)
                    .append(
                        $('<button type="button" class="csc-ms-pill-remove" aria-label="Remove">\u00d7</button>')
                            .on('click', function () {
                                setSelected(getSelected().filter(function (v) { return v !== val; }));
                                renderPills();
                                if ($dropdown.is(':visible')) buildDropdown($input.val());
                            })
                    )
                    .appendTo($pills);
            });
        }

        function normalise(s) { return (s + '').toLowerCase().replace(/[^a-z0-9 ]/g, ''); }

        function buildDropdown(query) {
            var all      = opts.getData();
            var selected = getSelected();
            var q        = normalise(query || '');
            var matches  = all.filter(function (item) {
                var val = getValue(item);
                if (selected.indexOf(val) !== -1) return false;
                return !q || normalise(getLabel(item)).indexOf(q) !== -1;
            });

            $dropdown.empty();
            if (!matches.length) {
                $('<li class="csc-typeahead-item csc-typeahead-item--empty">No options</li>').appendTo($dropdown);
            } else {
                matches.forEach(function (item) {
                    var val = getValue(item);
                    $('<li class="csc-typeahead-item" role="option">').text(getLabel(item))
                        .on('click', function () {
                            var sel = getSelected();
                            sel.push(val);
                            setSelected(sel);
                            renderPills();
                            $input.val('').focus();
                            buildDropdown('');
                        })
                        .appendTo($dropdown);
                });
            }
            $dropdown.show();
        }

        if (opts.preSelected && opts.preSelected.length) { setSelected(opts.preSelected); }
        renderPills();

        $input.on('focus click', function () { if ($dropdown.is(':hidden')) buildDropdown($input.val()); });
        $input.on('input', function () { buildDropdown($input.val()); });
        $input.on('keydown', function (e) { if (e.key === 'Escape') $dropdown.hide(); });

        $(document).on('click', function (e) {
            if (!$(e.target).closest($input.closest('.csc-ms-wrap')).length &&
                !$(e.target).closest($dropdown).length) {
                $dropdown.hide();
            }
        });
    }

    /* -----------------------------------------------------------------------
     * DIRECTORY FILTER TYPEAHEADS
     * --------------------------------------------------------------------- */
    if (typeof window.cscDirData !== 'undefined') {
        var dd = window.cscDirData;

        // Country (multi-select)
        makeMultiSelect({
            inputId:     '#df-country-vis',
            hiddenId:    '#df-country-val',
            dropdownId:  '#df-country-drop',
            pillsId:     '#df-country-pills',
            getData:     function () { return dd.countries; },
            preSelected: dd.selCountries || [],
        });

        // County (multi-select)
        makeMultiSelect({
            inputId:     '#df-county-vis',
            hiddenId:    '#df-county-val',
            dropdownId:  '#df-county-drop',
            pillsId:     '#df-county-pills',
            getData:     function () { return dd.ukCounties.map(function (c) { return c.name; }); },
            preSelected: dd.selCounties || [],
        });

        // Primary Industry (multi-select)
        makeMultiSelect({
            inputId:     '#df-industry-vis',
            hiddenId:    '#df-industry-val',
            dropdownId:  '#df-industry-drop',
            pillsId:     '#df-industry-pills',
            getData:     function () { return dd.industries; },
            preSelected: dd.selIndustries || [],
        });

        // IGP Category (multi-select)
        makeMultiSelect({
            inputId:     '#df-igp-vis',
            hiddenId:    '#df-igp-val',
            dropdownId:  '#df-igp-drop',
            pillsId:     '#df-igp-pills',
            getData:     function () { return dd.igpCats; },
            preSelected: dd.selIgp || [],
        });

        // Company Type (multi-select)
        makeMultiSelect({
            inputId:     '#df-type-vis',
            hiddenId:    '#df-type-val',
            dropdownId:  '#df-type-drop',
            pillsId:     '#df-type-pills',
            getData:     function () { return dd.companyTypes; },
            preSelected: dd.selCoTypes || [],
        });

        // Postcode (multi-select)
        makeMultiSelect({
            inputId:     '#df-postcode-vis',
            hiddenId:    '#df-postcode-val',
            dropdownId:  '#df-postcode-drop',
            pillsId:     '#df-postcode-pills',
            getData:     function () { return dd.postcodes || []; },
            preSelected: dd.selPostcodes || [],
        });
    }

    /* -----------------------------------------------------------------------
     * REPS — Company multi-select filter
     * --------------------------------------------------------------------- */
    if (typeof window.cscRepsOrgs !== 'undefined') {
        makeMultiSelect({
            inputId:     '#reps-co-vis',
            hiddenId:    '#reps-co-val',
            dropdownId:  '#reps-co-drop',
            pillsId:     '#reps-co-pills',
            getData:     function () { return window.cscRepsOrgs; },
            preSelected: window.cscRepsOrgsSel || [],
        });
    }

    /* -----------------------------------------------------------------------
     * MEMBER DIRECTORY
     * --------------------------------------------------------------------- */

    // Filter panel toggle (panel starts open; clicking collapses/expands)
    $('#csc-filter-toggle').on('click', function () {
        var $panel   = $('#csc-filter-panel');
        var $label   = $(this).find('.csc-filter-btn-label');
        var hidden   = $panel.attr('hidden') !== undefined;

        if (hidden) {
            $panel.removeAttr('hidden');
            $(this).addClass('is-active').attr('aria-expanded', 'true');
            $label.text('Close Filters');
        } else {
            $panel.attr('hidden', '');
            $(this).removeClass('is-active').attr('aria-expanded', 'false');
            $label.text('All Filters');
        }
    });

    // Clear filters
    $('#csc-clear-filters').on('click', function () {
        var $form = $('#csc-dir-form');
        $form.find('input[type="text"], input[type="search"], input[type="hidden"]').val('');
        $form.submit();
    });

    // Clickable table rows
    $(document).on('click', '.csc-dir-row', function (e) {
        if ($(e.target).is('a') || $(e.target).closest('a').length) return;
        var href = $(this).data('href');
        if (href) window.location.href = href;
    });

    /* -----------------------------------------------------------------------
     * DESKTOP SIDEBAR COLLAPSE
     * --------------------------------------------------------------------- */
    var $collapseBtn = $('#csc-sidebar-collapse-btn');
    var $desktopSidebar = $('#csc-sidebar');
    var COLLAPSE_KEY = 'csc_sidebar_collapsed';

    // Restore saved state on load
    if (localStorage.getItem(COLLAPSE_KEY) === '1') {
        $desktopSidebar.addClass('is-collapsed');
        $collapseBtn.attr('aria-label', 'Expand sidebar').attr('title', 'Expand sidebar');
    }

    $collapseBtn.on('click', function () {
        var collapsed = $desktopSidebar.toggleClass('is-collapsed').hasClass('is-collapsed');
        localStorage.setItem(COLLAPSE_KEY, collapsed ? '1' : '0');
        $collapseBtn
            .attr('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar')
            .attr('title',      collapsed ? 'Expand sidebar' : 'Collapse sidebar');
    });

    /* -----------------------------------------------------------------------
     * MOBILE SIDEBAR DRAWER
     * --------------------------------------------------------------------- */
    var $sidebar        = $('#csc-sidebar');
    var $overlay        = $('#csc-sidebar-overlay');
    var $toggleBtn      = $('#csc-sidebar-toggle');
    var $closeBtn       = $('#csc-sidebar-close');

    function openSidebar() {
        $sidebar.addClass('is-open');
        $overlay.addClass('is-visible').show();
        $toggleBtn.addClass('is-open').attr('aria-expanded', 'true');
        $('body').css('overflow', 'hidden');
    }

    function closeSidebar() {
        $sidebar.removeClass('is-open');
        $overlay.removeClass('is-visible');
        $toggleBtn.removeClass('is-open').attr('aria-expanded', 'false');
        $('body').css('overflow', '');
        // hide overlay after transition
        setTimeout(function () {
            if (!$overlay.hasClass('is-visible')) {
                $overlay.hide();
            }
        }, 280);
    }

    $toggleBtn.on('click', function () {
        if ($sidebar.hasClass('is-open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    });

    $closeBtn.on('click', closeSidebar);
    $overlay.on('click', closeSidebar);

})(jQuery);
