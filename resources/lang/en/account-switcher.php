<?php

return [

    'menu' => [
        'switch_to' => 'Switch to',
        'switch' => 'Switch',
        'manage' => 'Manage linked accounts',
        'password' => 'Password of the account you are switching to',
        'confirm_heading' => 'Switch to :account',
        'confirm_description' => 'Confirm you own this account by entering its password.',
        'confirm_description_no_password' => 'You will be signed in as :account without leaving this session. You can switch back at any time.',
    ],

    'impersonate' => [
        'label' => 'Impersonate',
    ],

    'banner' => [
        'message' => 'You are signed in as :impersonated, impersonating from :impersonator.',
        'switch_back' => 'Switch back',
    ],

    'developer_logins' => [
        'heading' => 'Developer logins (:environment)',
        'menu_heading' => 'Developer logins',
    ],

    'linked_accounts' => [
        'title' => 'Linked accounts',
        'subheading' => 'Accounts you can switch to from the "Switch to" menu. Use a lower-privilege account for daily work and switch to the full account only when you need it.',
        'empty_heading' => 'No linked accounts yet',
        'empty_description' => 'Link an existing account or create a sub-account to switch between them without signing out.',
        'link_description' => 'Enter the credentials of the account you want to link. Both accounts will be able to switch to each other.',
        'create_description' => 'Create a new account and link it to the one you are signed in with. Assign it fewer permissions afterwards.',
        'fields' => [
            'label' => 'Label',
            'account' => 'Account',
            'email' => 'E-mail address',
            'name' => 'Name',
            'account_password' => 'Password of that account',
            'new_password' => 'Password',
            'requires_password' => 'Ask for password when switching',
            'requires_password_help' => 'Recommended when switching to an account with more permissions.',
            'linked_at' => 'Linked',
        ],
        'actions' => [
            'link' => 'Link existing account',
            'create' => 'Create sub-account',
            'rename' => 'Rename',
            'unlink' => 'Unlink',
        ],
        'notifications' => [
            'linked' => 'Account linked.',
            'created' => 'Sub-account created and linked.',
            'unlinked' => 'Account unlinked.',
            'invalid_credentials' => 'No account matches those credentials.',
        ],
    ],

    'reasons' => [
        'impersonation' => 'Impersonation',
        'impersonation_ended' => 'Impersonation ended',
        'linked_account' => 'Linked account',
        'developer_login' => 'Developer login',
    ],

    'errors' => [
        'not_linked' => 'You cannot switch to that account.',
        'invalid_password' => 'The password is incorrect.',
        'while_impersonating' => 'Switching accounts is not available while impersonating.',
        'cannot_impersonate' => 'You are not allowed to impersonate that user.',
        'developer_logins_disabled' => 'Developer logins are not available.',
        'feature_disabled' => 'This feature is disabled.',
    ],

];
