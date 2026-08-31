# MathVerse Supabase email setup

These templates are designed for Supabase Auth. For the hosted project, open
**Supabase Dashboard → Authentication → Email Templates** and copy the matching
HTML file into each template.

| Supabase template | Suggested subject | File |
| --- | --- | --- |
| Confirm sign up | `Confirm your Math MetaVerse account` | `confirm-signup.html` |
| Reset password | `Reset your Math MetaVerse password` | `reset-password.html` |
| Change email address | `Confirm your new Math MetaVerse email` | `change-email-address.html` |
| Password changed notification | `Your Math MetaVerse password was changed` | `password-changed.html` |
| Email address changed notification | `Your Math MetaVerse email was changed` | `email-address-changed.html` |

In **Authentication → URL Configuration**:

1. Set the Site URL to the deployed MathVerse root URL.
2. Add the deployed root URL and `/reset-password` page to the Redirect URLs.
3. Keep `APP_URL` set to that same deployed root URL in Laravel.

The Laravel recovery request passes the complete `/reset-password` URL as
`redirect_to`. For that reason, the reset template deliberately uses:

```html
{{ .RedirectTo }}?token_hash={{ .TokenHash }}&amp;type=recovery
```

Do not append `/reset-password` again in the Supabase template.

To use email changes and both security messages:

1. Enable email changes and email confirmations in Supabase Auth.
2. Enable secure/double email-change confirmation if both the old and new
   addresses should approve a change.
3. Enable the **Password changed** and **Email address changed** security
   notifications. Editing their HTML alone does not enable delivery.

The matching local Supabase CLI settings are included in `supabase/config.toml`.
