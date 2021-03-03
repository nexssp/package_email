<?php
$NexssStdin = fgets(STDIN);
$parsedJson = json_decode($NexssStdin, true);

// -------------------------------------------------------------
// EMAIL - LOAD NEXSS PROGRAMMER PHP LIBRARIES
// -------------------------------------------------------------

require_once(getenv("NEXSS_PACKAGES_PATH") . "/Nexss/Lib/NexssEnv.php");
require_once(genv("NEXSS_PACKAGES_PATH") . "/Nexss/Lib/NexssLog.php");
require_once(genv("NEXSS_PACKAGES_PATH") . "/Nexss/Lib/NexssFormat.php");

// EMAIL REQUIRED FIELD, AND DISPLAY EXAMPLE OF CONFIGURATION
if (!@$parsedJson['__imap_username']) {
    nxsWarn("IMAP seems to be not configured.");
    nxsWarn("Create config.json and eg run: nexss config.json | nexss Email");
    nxsWarn("\nExample config file:\n" . file_get_contents(__DIR__ . '/config.example.json'));
    exit();
}

use Nexss\Format;

// -------------------------------------------------------------
// EMAIL - SETTINGS, PARAMETERS
// -------------------------------------------------------------


$timezone = genv("PHP_DEFAULT_TIMEZONE");
if (@$parsedJson['nxsTimezone']) {
    $timezone = $parsedJson['nxsTimezone'];
}

date_default_timezone_set($timezone);

$host = genv("IMAP_DEFAULT_HOST");
if (@$parsedJson['imap_host']) {
    $host = $parsedJson['imap_host'];
}

$port = genv("IMAP_DEFAULT_PORT");
if (@$parsedJson['imap_port']) {
    if (!is_numeric($parsedJson['imap_port'])) {
        throw new Exception('Imap port must be a number.');
    }
    $port = $parsedJson['imap_port'];
}

$emailMaxFetch = genv("EMAIL_MAX_FETCH", 100);
if (@$parsedJson['emailMaxFetch']) {
    if (!is_numeric($parsedJson['emailMaxFetch']) && $parsedJson['emailMaxFetch'] !== "ALL") {
        throw new Exception('emailMaxFetch must be a number or ALL.');
    }
    if ($parsedJson['emailMaxFetch'] === "ALL") {
        $emailMaxFetch = "*";
    } else {
        // numeric value
        $emailMaxFetch = $parsedJson['emailMaxFetch'];
    }
}

$address = "$host:$port";
$mailboxURL = "{" . $address . "/imap/ssl}INBOX";

if (function_exists('imap_open')) {
    $inbox = imap_open($mailboxURL, @$parsedJson['__imap_username'], @$parsedJson['__imap_password']);
    if (!$inbox) {
        nxsError("can't connect: " . imap_last_error());
    }
    if (isset($parsedJson['imap_download_path'])) {
        $imapDownloadsPath = $parsedJson['imap_download_path'];
    }

    if (!@$parsedJson['nxsKeepVars']) {
        unset($parsedJson['imap_host']);
        unset($parsedJson['imap_port']);
        unset($parsedJson['__imap_username']);
        unset($parsedJson['__imap_password']);
        unset($parsedJson['imap_download_path']);

        unset($parsedJson['nxsKeepVars']);
    }

    if (@$parsedJson['emailStatus']) {
        $inboxCheck = imap_check($inbox);
        $parsedJson['Mailbox'] = $inboxCheck->Mailbox;
        $parsedJson['MailboxDate'] = $inboxCheck->Date;
        $parsedJson['MailboxNMsgs'] = $inboxCheck->Nmsgs;
        $parsedJson['MailboxRecent'] = $inboxCheck->Recent;

        unset($parsedJson['emailStatus']);
    }

    // -------------------------------------------------------------
    // EMAIL - SEARCH EMAILS
    // -------------------------------------------------------------
    $result = null;
    if (@$parsedJson['emailSearch']) {
        try {
            if (is_numeric($parsedJson['emailSearch'])) {
                $searchCriteria = $parsedJson['emailSearch'] . ":" . $emailMaxFetch;
                $emails =
                    imap_fetch_overview($inbox, $searchCriteria, FT_UID); //FT_UID
            } else {
                $searchCriteria = $parsedJson['emailSearch'];

                $emails = imap_search($inbox, $searchCriteria, FT_UID);
                // imap_search only returns the uids(FT_UID) so we get emails with fetch overview
                if ($emails && is_array($emails)) {
                    $emails =
                        imap_fetch_overview($inbox, implode(",", $emails), FT_UID);
                }
            }
            $parsedJson['searchCriteria'] = $searchCriteria;
            unset($parsedJson['emailSearch']);
        } catch (\Throwable $th) {
            nxsError("Search criteria: $searchCriteria");
            nxsError("Error message: " . $th->getMessage());
            exit(1);
        }
        if (is_array($emails)) {
            $result = array_map(function ($email) use ($inbox) {
                $subject = imap_mime_header_decode($email->subject);

                return [
                    "subject" => $subject[0]->text,
                    "from" => $email->from,
                    "to" => $email->to,
                    "date" => $email->date,
                    "size" => Format::Bytes($email->size),
                    "seen" => $email->seen,
                    "uid" => $email->uid,
                    "message_id" => $email->message_id,
                    "date" => date("Y-m-d H:i:s", $email->udate),
                    "udate" => $email->udate
                ];
            }, $emails);

            if ($result) {
                $lastMessage = end($result);
                $lastMessageUID = end($result)['uid'];
                $parsedJson['emailLastUID'] = $lastMessageUID;
                $parsedJson['emailTotal'] = count($result);
                $parsedJson['emailFound'] = $result;
            } else {
                $parsedJson['emailTotal'] = 0;
            }
        } else {
            nxsWarn("No emails found");
        }
    }


    // -------------------------------------------------------------
    // EMAIL - ATTACHMENTS
    // -------------------------------------------------------------

    function is_absolute_path($path)
    {
        if ($path === null || $path === '') throw new Exception("Empty path");
        return $path[0] === DIRECTORY_SEPARATOR || preg_match('~\A[A-Z]:(?![^/\\\\])~i', $path) > 0;
    }

    if (@$parsedJson['attachmentType'] || @$parsedJson['attachmentRegexp']) {
        $regexp = '';

        if (@$parsedJson['attachmentType']) {
            if (!is_array($parsedJson['attachmentType'])) {
                $parsedJson['attachmentType'] = [$parsedJson['attachmentType']];
            }

            $regexp = "/^.*\.(" . implode("|", $parsedJson['attachmentType']) . ")$/i";
        }

        if (@$parsedJson['attachmentRegexp']) {
            $regexp = $parsedJson['attachmentRegexp'];
        }

        $parsedJson['attachmentSearchRegExp'] = $regexp;
        unset($parsedJson['attachmentRegexp']);
        unset($parsedJson['attachmentType']);
        // Email download PATH
        if (isset($imapDownloadsPath)) {
            $emailAttachmentsDownloadsPath = $imapDownloadsPath;
        } else {
            if (isset($parsedJson['emailDownloadPath'])) {
                if (is_absolute_path($parsedJson['emailDownloadPath'])) {
                    $emailAttachmentsDownloadsPath = $parsedJson['emailDownloadPath'];
                } else {
                    $emailAttachmentsDownloadsPath = __DIR__ . "/" . $parsedJson['emailDownloadPath'];
                }
            } else {
                $emailAttachmentsDownloadsPath = $parsedJson['cwd'] . "/" . genv('EMAIL_DOWNLOAD_ATTACHMENTS_PATH');
            }
        }



        if (!file_exists($emailAttachmentsDownloadsPath)) {
            mkdir($emailAttachmentsDownloadsPath, 0777, true);
        }

        /* put the newest emails on top */
        // rsort($emails);
        $files = [];
        foreach ($emails as $email) {
            $email_number = $email->uid;
            //     $overview  = imap_fetch_overview($imap, $uid, FT_UID);
            //     $headers   = imap_fetchbody($imap, $uid, 0, FT_UID); // message
            //     $plaintext = imap_fetchbody($imap, $uid, 1, FT_UID);
            //     $html      = imap_fetchbody($imap, $uid, 2, FT_UID);
            $structure = imap_fetchstructure($inbox, $email_number, FT_UID);
            $attachments = array();
            if (isset($structure->parts) && count($structure->parts)) {
                for ($i = 0; $i < count($structure->parts); $i++) {
                    $filename = '';
                    $attachments[$i] = array(
                        'is_attachment' => false,
                        'filename' => '',
                        'name' => '',
                        'attachment' => ''
                    );

                    if ($structure->parts[$i]->ifdparameters) {
                        foreach ($structure->parts[$i]->dparameters as $object) {
                            if (strtolower($object->attribute) == 'filename') {
                                $attachments[$i]['is_attachment'] = true;
                                $attachments[$i]['filename'] = $object->value;

                                $filename = $object->value;
                            } elseif (strtolower($object->attribute) == 'name') {
                                $attachments[$i]['is_attachment'] = true;
                                $attachments[$i]['name'] = $object->value;
                            }
                        }
                    }
                    if ($filename) {
                        if (!preg_match($regexp, $filename)) {
                            nxsDebug("Attachment $filename not matched.");
                            unset($attachments[$i]);
                            continue;
                        } else {
                            nxsDebug("Attachment $filename matched.");
                        }
                    }

                    if ($attachments[$i]['is_attachment']) {
                        $attachments[$i]['attachment'] = imap_fetchbody($inbox, $email_number, $i + 1, FT_UID);
                        if ($structure->parts[$i]->encoding == 3) { // 3 = BASE64
                            $attachments[$i]['attachment'] = base64_decode($attachments[$i]['attachment']);
                        } elseif ($structure->parts[$i]->encoding == 4) { // 4 = QUOTED-PRINTABLE
                            $attachments[$i]['attachment'] = quoted_printable_decode($attachments[$i]['attachment']);
                        }
                    }
                }
            }

            foreach ($attachments as $at) {
                if ($at['is_attachment']) {
                    $file = $emailAttachmentsDownloadsPath . "/" . $email_number . "_" . imap_utf8($at['filename']);
                    file_put_contents($file, $at['attachment']);
                    $files[] = str_replace($parsedJson['cwd'], ".", $file);
                }
            }
        }
        if ($files) {
            $parsedJson['emailStoredAttachments'] = $files;
        }
    }

    imap_close($inbox);
} else {
    $parsedJson['nxsStop'] = true;
    $parsedJson['nxsStopReason'] = "imail_open function does not exist.";
}


$NexssStdout = json_encode($parsedJson, JSON_UNESCAPED_UNICODE);

# STDOUT
fwrite(STDOUT, $NexssStdout);
