# MJJ bbP Subscription

This is a simple plugin which replaces bbp_notify_subscribers. It adds in a one click unsubscribe link as well as a link to the user's subscription page. To avoid confusion, the unsubscribe link won't work if a user is logged in with a different account. However, it will work if a user is not logged on and, as you might expect, if they are logged on with account to whom the email was sent. It also adds a clear reminder that it's not possible to post by email.

The email sends to one subscriber at a time but queued in a cron job. The emails are now html.

This doesn't handle forum subscriptions because I don't use them (I just use topic subscriptions) but you could add them in if you wanted.

### a note on using this branch:

If you switch to this branch from the other, your unsubscribe links sent using the previous version won't work.
