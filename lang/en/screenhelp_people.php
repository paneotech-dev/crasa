<?php

return [
    'admin.users.show' => ['purpose' => 'One account: its details and every audit entry about it or made by it, newest first.', 'actions' => ['Edit the account', 'Show entries about it or by it', 'Page through the entries']],
    'admin.users.edit' => ['purpose' => 'Change an account\'s name, email, role, member state, regulator and committee seats; every change is audited.', 'actions' => ['Change the details', 'Set committee seats', 'Save account']],
    'admin.registrations' => ['purpose' => 'Every delegate registration, grouped by event, from filing to confirmation.', 'actions' => ['Confirm or decline a registration', 'Resend the delegate\'s copy', 'Export to CSV or Excel']],
];
