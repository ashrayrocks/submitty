<?php

namespace app\authentication;
use app\exceptions\AuthenticationException;
use app\exceptions\CurlException;
use app\libraries\Core;
use app\libraries\FileUtils;

/**
 * Class PamAuthentication
 *
 * Module that utilizes PAM (@link http://www.linux-pam.org/) to handle authentication.
 * Unfortunately, the PHP-PAM package (@link https://pecl.php.net/package/PAM) is depreciated
 * so to do this requires using a cgi script (@see cgi-bin/pam_check.cgi) that runs python to
 * use its supported PAM module. We save the username/password to a tmp file, pass the random
 * filename to the cgi script via GET, then saves the results to another tmp file passing back
 * the filename via GET to this page, all using the cURL library.
 */
class PamAuthentication extends AbstractAuthentication {
    public function authenticate() {
        if ($this->user_id === null || $this->password === null ||
            $this->core->getQueries()->getSubmittyUser($this->user_id) === null) {
            return false;
        }

        try {
            // Open a cURL connection so we don't have to do a weird redirect chain to authenticate
            // as that would require some hacky path handling specific to PAM authentication
            $output = $this->core->curlRequest(
                $this->core->getConfig()->getCgiUrl()."pam_check.cgi",
                [
                    'username' => $this->user_id,
                    'password' => $this->password,
                ]
            );

            $output_after = json_decode($output, true);
            if ($output_after === null) {
                throw new AuthenticationException("Error JSON response for PAM: ".json_last_error_msg());
            }
            else if (!isset($output_after['authenticated'])) {
                throw new AuthenticationException('Missing response in JSON for PAM');
            }
            else if ($output_after['authenticated'] !== true) {
                return false;
            }
        }
        catch (CurlException $exc) {
            throw new AuthenticationException('Error attempting to authenticate against PAM: '.$exc->getMessage());
        }

        return true;
    }
}
