# Email for Nexss Programmer

Email read and send operations.

## Examples

```sh
nexss Email --emailStatus # this will display status 'Mailbox' name, 'MailboxNMsgs', 'MailboxRecent'

nexss Email --attachmentType="pdf" --attachmentType="jpg" # This will download all attachments from the last 100 emails

nexss Email --emailSearch=12 # if is only a number will get info from email from uid "12:100". You can overwrite 100 by emailMaxFetch, see below.
nexss Email --emailMaxFetch=ALL # will ommit default 100 emails limit and saerch for all emails
nexss Email --emailMaxFetch=20 # set limit to 20 emails at once

nexss Email --emailSearch="FROM mapoart@gmail.com" # more here: <https://www.php.net/manual/en/function.imap-search.php>
nexss Email --emailSearch="UNSEEN"

nexss Email --nxsTimezone # list of available timezones https://www.php.net/manual/en/timezones.europe.php
```

### Parameters

- **nxsKeepVars** - will keep config of IMAP after connections.
- **attachmentType** - Can be multiple. eg --attachmentType=pdf --attachmentType=doc
- **attachmentRegexp** - Regular expression (will overwrite attachmentType parameter)
- **emailSearch** - IMAP Search, more here: <https://www.php.net/manual/en/function.imap-search.php>

### Output

- **lastEmailUID** Last checked email. Can be handy with checking emails periodically.
-

### Receive

```sh
nexss Email

```

### Send

TODO: implement Sending

```sh
nexss Email send --subject="This is my subject" --message="This is long message\n test" --attachment=file1.jpg attachment=file2.jpg

```

## Search Criteria Examples

sys.path.append(os.getenv("NEXSS_PACKAGES_PATH") + "\\Nexss\\Lib\\")

```php

- 'FROM gmail.com'
- 'FROM Marcin'
- 'FROM RECENT' // or 'NOT RECENT' / 'OLD'
- 'DELETED';
- 'HEADER DomainKey-Signature paypal.com';
- 'HEADER DomainKey-Signature \'\''; //If second string empty then will find all with the header
- 'NEW';
- 'SEEN'; // NOT SEEN
- 'LARGER 500000'; // find emails larger then certain bytes
- 'SMALLER 500'; // find emailslarger then certain bytes
- 'FROM mapoart@gmail.com';
```
