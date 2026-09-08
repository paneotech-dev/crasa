<?php

return [
    'sign_in' => 'Sign in',
    'sign_out' => 'Sign out',
    'intro' => "One login reaches the extranet and the members' workspace. Access is granted by the Secretariat on the instruction of your authority.",
    'email' => 'Email address',
    'password' => 'Password',
    'remember' => 'Stay signed in on this device',
    'forgot' => 'Forgotten your password?',
    'logged_note' => 'Sign-in attempts and downloads are logged against your account.',

    // Never says which of the two was wrong, and reads the same for an
    // address that holds no account, so the form cannot be used to find out
    // who has one. Screen 11 states this explicitly.
    'failed' => 'That email address and password do not match an active account. :remaining attempts remain before the account is locked for 30 minutes.',
    'locked' => 'Too many attempts. Try again in :minutes minutes, or contact the Secretariat.',
    'throttle' => 'Too many attempts. Try again in :seconds seconds.',

    'two_factor' => 'Two-factor authentication',
    'challenge_intro' => 'Enter the six-digit code from the authenticator app registered to this account.',
    'authenticator_code' => 'Authenticator code',
    'code_hint' => 'Six digits, from the app on your phone.',
    'verify' => 'Verify',
    'code_invalid' => 'That code is not correct. Check the app and try the current code.',
    'use_recovery' => 'Use a recovery code instead',
    'recovery_code' => 'Recovery code',
    'recovery_hint' => 'One of the codes you saved when you enrolled. Each works once.',
    'recovery_invalid' => 'That recovery code is not valid, or it has already been used.',

    'enrol_title' => 'Set up two-factor authentication',
    'enrol_intro' => 'Scan the code with an authenticator app, then confirm the first six digits.',
    'mandatory_title' => 'Required for this account',
    'mandatory_body' => 'Two-factor authentication is mandatory for administrators. This account reaches nothing else until enrolment is complete.',
    'cannot_scan' => 'Cannot scan the code?',
    'manual_intro' => 'Type this key into the app instead. It is offered as text because a delegate on a shared device cannot always scan.',
    'confirm_code' => 'Code from the app',
    'confirm_enable' => 'Confirm and enable',
    'enrolled_title' => 'Two-factor authentication is on',
    'enrolled_body' => 'This account now asks for a code at every sign-in.',

    'codes_once_title' => 'Save these recovery codes now',
    'codes_once_body' => 'They will not be shown again. They are stored hashed, so nobody, including the Secretariat, can read them back. Each code works once. Store them outside this platform.',
    'codes_remaining' => '{0} No recovery codes remain. Generate a new set.|{1} One recovery code remains.|[2,*] :count recovery codes remain.',
    'regenerate_codes' => 'Generate a new set of recovery codes',
    'turn_off' => 'Turn off two-factor authentication',
    'two_factor_mandatory' => 'Two-factor authentication cannot be turned off for an administrator.',
    'not_enrolled' => 'This account is not enrolled in two-factor authentication.',
    'manage_two_factor' => 'Manage two-factor authentication',

    'reset_title' => 'Reset your password',
    'reset_intro' => 'Enter your address and a reset link will be sent to it.',
    'send_link' => 'Send the reset link',
    // Identical whether or not the address holds an account.
    'reset_sent' => 'If that address holds an account, a reset link is on its way to it.',
    'choose_password' => 'Choose a new password',
    'password_rule' => 'At least twelve characters, with letters and numbers.',
    'new_password' => 'New password',
    'confirm_password' => 'Confirm the new password',
    'set_password' => 'Set the password',
    'reset_done' => 'Your password has been changed. Sign in with it.',
];
