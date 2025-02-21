<?php
// Copyright (c) 2025 Bennet Becker <dev@bennet.cc>
//
// Permission is hereby granted, free of charge, to any person obtaining a copy
// of this software and associated documentation files (the "Software"), to deal
// in the Software without restriction, including without limitation the rights
// to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
// copies of the Software, and to permit persons to whom the Software is
// furnished to do so, subject to the following conditions:
//
// The above copyright notice and this permission notice shall be included in all
// copies or substantial portions of the Software.
//
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
// IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
// FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
// AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
// LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
// OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
// SOFTWARE.

/** @noinspection PhpUnused */

class mailmerge extends \rcube_plugin
{
    private rcmail $rcmail;

    private static function log(...$lines): void
    {
        foreach ($lines as $line) {
            $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
            $func = $bt["function"];
            $cls = $bt["class"];
            if (!is_string($line)) {
                $line = print_r($line, true);
            }
            $llines = explode(PHP_EOL, $line);
            rcmail::write_log('mailmerge', "[mailmerge] {" . $cls . "::" . $func . "} " . $llines[0]);
            unset($llines[0]);
            if (count($llines) > 0) {
                foreach ($llines as $l) {
                    rcmail::write_log('mailmerge', str_pad("...", strlen("[mailmerge] "), " ", STR_PAD_BOTH) . "{" . $cls . "::" . $func . "} " . $l);
                }
            }
        }
    }


    public function init(): void
    {
        $this->rcmail = rcmail::get_instance();

        $this->register_action('plugin.mailmerge', [$this, 'mailmerge_action']);

        $this->add_hook("message_saved", function ($param) {
            self::log($param);
        });

        $this->add_hook("ready", function ($param) {
            self::log('ready', $param);
            if ($param['task'] == 'mail' && $param['action'] === 'compose') {

                $header = \html::div('row', \html::div('col-12', \html::span("font-weight-bold", "Mail merge options")));

                $sselect = new html_select(["id" => "mailmergesep"]);
                $sselect->add([", (Comma)", "; (Semicolon)", "| (Pipe)", "Tab"], [",", ";", "|", "tab"]);
                $separator = \html::div("form-group row",
                    \html::label(['for' => 'mailmergesep', 'class' => 'col-form-label col-6'], rcube::Q("Separator")) .
                    \html::div('col-6', $sselect->show(["id" => "mailmergesep", "class" => "custom-select form-control pretty-select"])));

                $enclosed = html::div('form-group form-check row',
                    html::label(['for' => 'mailmergeencl', 'class' => 'col-form-label col-6'],
                        rcube::Q("Enclosed Fields")
                    )
                    . html::div('form-check col-6',
                        (new html_checkbox(["value" => 1]))->show(1, [
                            'name' => '_mailmerge_encl',
                            'id' => 'mailmergeencl',
                            'class' => 'form-check-input'
                        ])
                    )
                );

                $file = html::div('form-group form-check row',
                    \html::div("col-6", (new html_inputfield(["type" => "file"]))->show(null, ["id" => "mailmergefile"])) .
                    \html::div("col-6", (new html_button([
                        'type' => 'button',
                        'command' => 'plugin.mailmerge',
                        'onclick' => "rcmail.command('plugin.mailmerge', '', event.target, event)",
                        'class' => 'button mailmerge mx-4',
                        'title' => 'mailmerge',
                        'label' => 'mailmerge',
                        'domain' => $this->ID,
                        'width' => 32,
                        'height' => 32,
                        'aria-owns' => 'mailmerge',
                        'aria-haspopup' => 'false',
                        'aria-expanded' => 'false',
                    ]))->show(rcube::Q('mailmerge')))
                );

                $this->api->add_content(\html::div(["style" => "padding-bottom: 1rem; margin: 0", "class" => "file-upload"], $header . $separator . $enclosed . $file), 'composeoptions');
                $this->include_script('mailmerge.js');
            }
        });
    }

    public static function replace_vars(string|null $str, array $dict): string|null
    {
        if (is_null($str)) {
            return null;
        }

        assert(is_string($str));
        return preg_replace_callback("({{({*.*?}*)}})", function ($matches) use ($dict) {
            $var = self::replace_vars($matches[1], $dict);
            if (str_contains($var, "|")) {
                $tokens = explode("|", $var);
                if (count($tokens) >= 5 && in_array($tokens[1], ['*', '^', '$', '==', '>', '>=', '<', '<='])) {
                    // Complex comparators
                    $val = array_key_exists($tokens[0], $dict) ? $dict[$tokens[0]] : "";
                    switch ($tokens[1]) {
                        case '*':
                            // {{name|*|if|then|else}} (includes)
                            // If the value of the field name includes if, then the variable will be replaced by then, else by else.
                            return str_contains($val, $tokens[2]) ? $tokens[3] : $tokens[4];
                        case '^':
                            // {{name|^|if|then|else}} (starts with)
                            // If the value of the field name starts with if, then the variable will be replaced by then, else by else.
                            return str_starts_with($val, $tokens[2]) ? $tokens[3] : $tokens[4];
                        case '$':
                            // {{name|$|if|then|else}} (ends with)
                            // If the value of the field name ends with if, then the variable will be replaced by then, else by else.
                            return str_ends_with($val, $tokens[2]) ? $tokens[3] : $tokens[4];
                        case '==':
                            // {{name|==|if|then|else}} (equal to) (number)
                            // If the value of the field name is equal to if, then the variable will be replaced by then, else by else.
                            return $val == $tokens[2] ? $tokens[3] : $tokens[4];
                        case '>':
                            // {{name|>|if|then|else}} (greater than) (number)
                            // If the value of the field name is greater than if, then the variable will be replaced by then, else by else.
                            return $val > $tokens[2] ? $tokens[3] : $tokens[4];
                        case '>=':
                            // {{name|>=|if|then|else}} (greater than or equal to) (number)
                            // If the value of the field name is greater than or equal to if, then the variable will be replaced by then, else by else.
                            return $val >= $tokens[2] ? $tokens[3] : $tokens[4];
                        case '<':
                            // {{name|<|if|then|else}} (less than) (number)
                            // If the value of the field name is less than if, then the variable will be replaced by then, else by else.
                            return $val < $tokens[2] ? $tokens[3] : $tokens[4];
                        case '<=':
                            // {{name|<=|if|then|else}} (less than or equal to) (number)
                            // If the value of the field name is less than or equal to if, then the variable will be replaced by then, else by else.
                            return $val <= $tokens[2] ? $tokens[3] : $tokens[4];
                    }
                } elseif (count($tokens) >= 3) {
                    // {{name|if|then|else}}
                    // {{name|if|then}}
                    if (array_key_exists($tokens[0], $dict) && $dict[$tokens[0]] === $tokens[1]) {
                        return $tokens[2];
                    } else {
                        return count($tokens) == 3 ? "" : $tokens[3];
                    }
                } else {
                    // Malformed?
                    return "";
                }
            } else {
                if (array_key_exists($var, $dict)) {
                    return $dict[$var];
                } else {
                    return "";
                }
            }
        }, $str);
    }

    public function mailmerge_action()
    {
        $input = filter_input_array(INPUT_POST, FILTER_UNSAFE_RAW);
        self::log($_REQUEST, $_FILES);
        $input["to"] = explode(",", $input["to"]);
        $input["cc"] = explode(",", $input["cc"]);
        $input["bcc"] = explode(",", $input["bcc"]);
        $input["replyto"] = explode(",", $input["replyto"]);
        $input["followupto"] = explode(",", $input["followupto"]);

//        $files =

        assert(count($_FILES) === 1, "File missing");

        $csv_data = [];
        // Read CSV
        if (($handle = fopen($_FILES['csv']['tmp_name'], "r")) !== FALSE) {
            while (($data = fgetcsv($handle, null, ";")) !== FALSE) {
                $csv_data[] = $data;
            }
            fclose($handle);
        }
        $csv_head = $csv_data[0];
        $csv_body = array_slice($csv_data, 1);
        $csv_data = null;

        $dict = array_map(function ($line) use ($csv_head) {
            return array_combine($csv_head, $line);
        }, $csv_body);

        @set_time_limit(360);

        foreach ($dict as &$line) {
            $mime = new Mail_mime();

            $identity = $this->rcmail->user->get_identity($input["from"]);

            $mime->headers([
                'Date' => $this->rcmail->user_date(),
                'User-Agent' => $this->rcmail->config->get('useragent'),
                'Message-ID' => $this->rcmail->gen_message_id($input["from"]),
            ]);

            if (!empty($identity['organization'])) {
                $mime->headers(["Organization" => $identity['organization']]);
            }

            $from = $identity['name'] . ' <' . $identity['email'] . '>';
            $mime->setFrom($from);

            $mime->setSubject(self::replace_vars($input["subject"], $line));

            foreach ($input["to"] as $recipient) {
                $mime->addTo(self::replace_vars($recipient, $line));
            }
            if (count($input["cc"]) > 0) {
                foreach ($input["cc"] as $recipient) {
                    $mime->addCc(self::replace_vars($recipient, $line));
                }
            }
            if (count($input["bcc"]) > 0) {
                foreach ($input["bcc"] as $recipient) {
                    $mime->addBcc(self::replace_vars($recipient, $line));
                }
            }
            if (count($input["replyto"]) > 0) {
                $rto = array_map(function ($rr) use ($line) {
                    self::replace_vars($rr, $line);
                }, $input["replyto"]);

                $mime->headers([
                    "Reply-To" => $rto,
                    "Mail-Reply-To" => $rto
                ]);
            }
            if (count($input["followupto"]) > 0) {
                $mime->headers(["Mail-Followup-To" => array_map(function ($rr) use ($line) {
                    self::replace_vars($rr, $line);
                }, $input["followupto"])]);
            }

            $message = self::replace_vars($input["message"], $line);
            if ($input["mode"] == "html") {
                $mime->setHTMLBody($message);
                $mime->setTXTBody($this->rcmail->html2text($message));
            } else {
                $mime->setTXTBody($message);
            }

            if ($input["_mdn"]) {
                $mime->headers(["Disposition-Notification-To" => $from]);
            }

            $a_priorities = [1 => 'highest', 2 => 'high', 4 => 'low', 5 => 'lowest'];

            if (!empty($a_priorities[$input["_priority"]])) {
                $mime->headers(['X-Priority' => sprintf('%d (%s)', $input["_priority"], ucfirst($a_priorities[$input["_priority"]]))]);
            }

            $COMPOSE    =& $_SESSION['compose_data_'.$input["compose_id"]];
            if (!isset($COMPOSE['attachments'])) {
                $COMPOSE['attachments'] = [];
            }

            $folding = (int)$this->rcmail->config->get('mime_param_folding');
            foreach ($COMPOSE['attachments'] as $attachment) {
                // This hook retrieves the attachment contents from the file storage backend
                $attachment = $this->rcmail->plugins->exec_hook('attachment_get', $attachment);
                $is_inline = false;
                $dispurl = null;
                $is_file = !empty($attachment['path']);
                $file = !empty($attachment['path']) ? $attachment['path'] : ($attachment['data'] ?? '');

                if ($input["mode"] == "html") {
                    $dispurl = '/[\'"]\S+display-attachment\S+file=rcmfile' . preg_quote($attachment['id']) . '[\'"]/';
                    $message_body = $mime->getHTMLBody();
                    $is_inline = preg_match($dispurl, $message_body);
                }

                $ctype = $attachment['mimetype'] ?? '';
                $ctype = str_replace('image/pjpeg', 'image/jpeg', $ctype); // #1484914

                // inline image
                if ($is_inline) {
                    // Mail_Mime does not support many inline attachments with the same name (#1489406)
                    // we'll generate cid: urls here to workaround this
                    $cid = preg_replace('/[^0-9a-zA-Z]/', '', uniqid(time(), true));
                    if (preg_match('#(@[0-9a-zA-Z\-\.]+)#', $from, $matches)) {
                        $cid .= $matches[1];
                    } else {
                        $cid .= '@localhost';
                    }

                    if ($dispurl && !empty($message_body)) {
                        $message_body = preg_replace($dispurl, '"cid:' . $cid . '"', $message_body);

                        rcube_utils::preg_error([
                            'message' => 'Could not replace an image reference!',
                        ], true);

                        $mime->setHTMLBody($message_body);
                    }

                    $mime->addHTMLImage($file, $ctype, $attachment['name'], $is_file, $cid);
                } else {
                    $mime->addAttachment($file,
                        $ctype,
                        $attachment['name'],
                        $is_file,
                        $ctype == 'message/rfc822' ? '8bit' : 'base64',
                        'attachment',
                        $attachment['charset'] ?? null,
                        '', '',
                        $folding ? 'quoted-printable' : null,
                        $folding == 2 ? 'quoted-printable' : null,
                        '', RCUBE_CHARSET
                    );
                }
            }

            $msg_str = $mime->getMessage();
            $this->rcmail->storage->save_message("Drafts", $msg_str);
        }
    }
}
