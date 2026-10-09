<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Stripe Subscriptions';
$string['stripe'] = 'Stripe';
$string['stripe:manage'] = 'Manage Stripe subscriptions';
$string['publishablekey'] = 'Publishable Key';
$string['publishablekey_desc'] = 'Your Stripe publishable key (starts with pk_)';
$string['secretkey'] = 'Secret Key';
$string['secretkey_desc'] = 'Your Stripe secret key (starts with sk_)';
$string['webhooksecret'] = 'Webhook Signing Secret';
$string['webhooksecret_desc'] = 'Your Stripe webhook signing secret (starts with whsec_)';
$string['priceid'] = 'Price ID';
$string['priceid_desc'] = 'Default Stripe Price ID for subscriptions (starts with price_)';
$string['settings'] = 'Stripe Settings';
$string['privacy:metadata'] = 'The Stripe plugin does not store any personal data.';

$string['managebilling'] = 'Gestionar Suscripción';
$string['subscriptionmenu'] = 'Subscription';
$string['taskreconcilesubscriptions'] = 'Reconcile Stripe subscriptions';
$string['taskexpiremanualaccess'] = 'Expire offline-payment access';
$string['viewplans'] = 'Planes de Suscripción';
$string['subscribenow'] = 'Suscribirme';
$string['manualaccesstitle'] = 'Offline payment access';
$string['manualaccessdescription'] = 'Grant, renew, or cancel one month of access without creating a Stripe charge.';
$string['manualgrantheading'] = 'Record offline payment';
$string['manualuseridentifier'] = 'Email or username';
$string['manualuserplaceholder'] = 'Email or username';
$string['manualgrantbutton'] = 'Grant one month';
$string['manualrenewalhelp'] = 'If offline access is already active, the new month starts after the current expiry date.';
$string['manualusernotfound'] = 'Enter the full email or username of a confirmed, unsuspended account. If several accounts share an email, use the username.';
$string['manualgrantsuccess'] = 'Access was granted to {$a->user} until {$a->date}.';
$string['manualgrantfailed'] = 'Access could not be granted. Check that the account is confirmed, is not suspended, and the subscriber role exists. Retrying this form will not duplicate the payment.';
$string['manualcancelbutton'] = 'Cancel offline access';
$string['manualcancelconfirmtitle'] = 'Confirm cancellation';
$string['manualcancelconfirm'] = 'All current and prepaid offline-payment months for this person will be cancelled. An active Stripe subscription will continue to provide access. This action does not issue refunds or cancel Stripe billing.';
$string['manualcancelreason'] = 'Reason (optional)';
$string['manualcancelsuccess'] = 'Offline access was cancelled.';
$string['manualnothingtocancel'] = 'The user no longer has active offline access.';
$string['manualhistory'] = 'Offline access history';
$string['manualnohistory'] = 'No offline payments have been recorded yet.';
$string['manualstart'] = 'Start';
$string['manualend'] = 'Expiry';
$string['manualcreatedby'] = 'Recorded by';
$string['manualstatusactive'] = 'Active';
$string['manualstatusexpired'] = 'Expired';
$string['manualstatuscancelled'] = 'Cancelled';
$string['manualstatusscheduled'] = 'Prepaid month';
$string['manualsearchbutton'] = 'Find user';
$string['manualrenewbutton'] = 'Add one month';
$string['manualperiod'] = 'Start and expiry';
$string['manualcancellation'] = 'Cancellation';
$string['manualpending'] = 'Access check pending';
$string['manualcancelpending'] = 'The offline cancellation was recorded. Stripe could not be verified; access is temporarily retained and will be checked again automatically.';
$string['manualcancelretained'] = 'Offline access was cancelled. The person retains access through their Stripe subscription.';
$string['manualcancelfailed'] = 'The cancellation check could not be completed. Check the history before retrying; pending cancellations are checked automatically.';
$string['manualalreadyprocessed'] = 'This payment was already recorded. Check its status in the history.';
$string['manualbusy'] = 'Access for this person is being updated. Try again in a few seconds.';
$string['manualyouraccess'] = 'Your offline payment access';
$string['manualyouraccessuntil'] = 'Your offline access is valid until {$a}. Contact the administrator to renew it.';
$string['manualstripebilling'] = 'Manage Stripe billing';
