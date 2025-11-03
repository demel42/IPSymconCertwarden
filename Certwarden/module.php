<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';

class Certwarden extends IPSModule
{
    use Certwarden\StubsCommonLib;
    use CertwardenLocalLib;

    public function __construct(string $InstanceID)
    {
        parent::__construct($InstanceID);

        $this->CommonConstruct(__DIR__);
    }

    public function __destruct()
    {
        $this->CommonDestruct();
    }

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyString('server', '');
        $this->RegisterPropertyInteger('port', 4055);

        $this->RegisterPropertyString('cert_name', '');
        $this->RegisterPropertyString('cert_apikey', '');

        $this->RegisterPropertyString('key_name', '');
        $this->RegisterPropertyString('key_apikey', '');

        $this->RegisterPropertyInteger('webserver_instID', 0);

        $this->RegisterPropertyString('script', '');

        $this->RegisterPropertyInteger('update_interval', 60);

        $this->RegisterAttributeString('UpdateInfo', json_encode([]));
        $this->RegisterAttributeString('ModuleStats', json_encode([]));

        $this->InstallVarProfiles(false);

        $this->RegisterTimer('UpdateCertificate', 0, 'IPS_RequestAction(' . $this->InstanceID . ', "UpdateCertificate", "");');

        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
    }

    public function MessageSink($timestamp, $senderID, $message, $data)
    {
        parent::MessageSink($timestamp, $senderID, $message, $data);

        if ($message == IPS_KERNELMESSAGE && $data[0] == KR_READY) {
            $module_disable = $this->ReadPropertyBoolean('module_disable');
            if ($module_disable == false) {
                $this->MaintainTimer('UpdateCertificate', 5 * 1000);
            }
        }

        $webserver_instID = $this->ReadPropertyInteger('webserver_instID');
        if (IPS_GetKernelRunlevel() == KR_READY && $message == IM_CHANGESTATUS && $senderID == $webserver_instID) {
            $this->SendDebug(__FUNCTION__, 'timestamp=' . $timestamp . ', senderID=' . $senderID . ', message=' . $message . ', data=' . print_r($data, true), 0);
            if ($data[0] != $data[1]) {
                $this->SetValue('WebServerStatus', $data[0]);
            }
        }
    }

    private function CheckModuleConfiguration()
    {
        $r = [];

        $server = $this->ReadPropertyString('server');
        if ($server == '') {
            $this->SendDebug(__FUNCTION__, '"server" is needed', 0);
            $r[] = $this->Translate('Server must be specified');
        }

        $cert_name = $this->ReadPropertyString('cert_name');
        if ($cert_name == '') {
            $this->SendDebug(__FUNCTION__, '"cert_name" is needed', 0);
            $r[] = $this->Translate('Certificate name must be specified');
        }
        $cert_apikey = $this->ReadPropertyString('cert_apikey');
        if ($cert_apikey == '') {
            $this->SendDebug(__FUNCTION__, '"cert_apikey" is needed', 0);
            $r[] = $this->Translate('Certificate API key must be specified');
        }
        $key_name = $this->ReadPropertyString('key_name');
        if ($key_name == '') {
            $this->SendDebug(__FUNCTION__, '"key_name" is needed', 0);
            $r[] = $this->Translate('Private key name must be specified');
        }
        $key_apikey = $this->ReadPropertyString('key_apikey');
        if ($key_apikey == '') {
            $this->SendDebug(__FUNCTION__, '"key_apikey" is needed', 0);
            $r[] = $this->Translate('Private key API key must be specified');
        }

        $webserver_instID = $this->ReadPropertyInteger('webserver_instID');
        if ($this->IsValidID($webserver_instID) == false) {
            $this->SendDebug(__FUNCTION__, '"webserver_instID" is needed', 0);
            $r[] = $this->Translate('Webserver instance must be specified');
        }

        return $r;
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $propertyNames = ['webserver_instID'];
        $this->MaintainReferences($propertyNames);

        $webserver_instID = $this->ReadPropertyInteger('webserver_instID');
        if (IPS_InstanceExists($webserver_instID)) {
            $this->UnregisterMessage($webserver_instID, IM_CHANGESTATUS);
        }

        $propertyNames = ['script'];
        foreach ($propertyNames as $name) {
            $text = $this->ReadPropertyString($name);
            $this->MaintainReferences4Script($text);
        }

        if ($this->CheckPrerequisites() != false) {
            $this->MaintainTimer('UpdateCertificate', 0);
            $this->MaintainStatus(self::$IS_INVALIDPREREQUISITES);
            return;
        }

        if ($this->CheckUpdate() != false) {
            $this->MaintainTimer('UpdateCertificate', 0);
            $this->MaintainStatus(self::$IS_UPDATEUNCOMPLETED);
            return;
        }

        if ($this->CheckConfiguration() != false) {
            $this->MaintainTimer('UpdateCertificate', 0);
            $this->MaintainStatus(self::$IS_INVALIDCONFIG);
            return;
        }

        $vpos = 1;
        $this->MaintainVariable('ValidUntil', $this->Translate('Certificate valid until'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);
        $this->MaintainVariable('WebServerStatus', $this->Translate('Webserver status'), VARIABLETYPE_INTEGER, 'Certwarden.WebserverStatus', $vpos++, true);

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->MaintainTimer('UpdateCertificate', 0);
            $this->MaintainStatus(IS_INACTIVE);
            return;
        }

        $this->MaintainStatus(IS_ACTIVE);

        if (IPS_InstanceExists($webserver_instID)) {
            $this->RegisterMessage($webserver_instID, IM_CHANGESTATUS);
        }

        if (IPS_GetKernelRunlevel() == KR_READY) {
            $this->MaintainTimer('UpdateCertificate', 5 * 1000);
        }
    }

    private function GetFormElements()
    {
        $formElements = $this->GetCommonFormElements('Cert Warden');

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            return $formElements;
        }

        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'module_disable',
            'caption' => 'Disable instance',
        ];

        $formElements[] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Certwarden server',
            'items'   => [
                [
                    'type'    => 'ValidationTextBox',
                    'name'    => 'server',
                    'caption' => 'Server'
                ],
                [
                    'type'    => 'NumberSpinner',
                    'name'    => 'port',
                    'caption' => 'Port'
                ],
            ],
        ];

        $formElements[] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Certificate',
            'items'   => [
                [
                    'type'    => 'RowLayout',
                    'items'   => [
                        [
                            'type'    => 'Label',
                            'caption' => 'Certificate',
                        ],
                        [
                            'type'    => 'ValidationTextBox',
                            'name'    => 'cert_name',
                            'caption' => 'Name',
                            'width'   => '200px',
                        ],
                        [
                            'type'    => 'ValidationTextBox',
                            'name'    => 'cert_apikey',
                            'caption' => 'API key',
                            'width'   => '400px',
                        ],
                    ],
                ],
                [
                    'type'    => 'RowLayout',
                    'items'   => [
                        [
                            'type'    => 'Label',
                            'caption' => 'Private key',
                        ],
                        [
                            'type'    => 'ValidationTextBox',
                            'name'    => 'key_name',
                            'caption' => 'Name',
                            'width'   => '200px',
                        ],
                        [
                            'type'    => 'ValidationTextBox',
                            'name'    => 'key_apikey',
                            'caption' => 'API key',
                            'width'   => '400px',
                        ],
                    ],
                ],
            ],
        ];

        $formElements[] = [
            'type'     => 'SelectModule',
            'moduleID' => '{D83E9CCF-9869-420F-8306-2B043E9BA180}',
            'name'     => 'webserver_instID',
            'caption'  => 'Webserver instance'
        ];

        $formElements[] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Script',
            'items'   => [
                [
                    'type'     => 'ScriptEditor',
                    'rowCount' => 10,
                    'name'     => 'script',
                    'caption'  => 'Script is called after execution. For more information, see documentation',
                ],
            ],
        ];

        $formElements[] = [
            'type'    => 'NumberSpinner',
            'name'    => 'update_interval',
            'suffix'  => 'Minutes',
            'minimum' => 0,
            'caption' => 'Update interval',
        ];

        return $formElements;
    }

    private function GetFormActions()
    {
        $formActions = [];

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            $formActions[] = $this->GetCompleteUpdateFormAction();

            $formActions[] = $this->GetInformationFormAction();
            $formActions[] = $this->GetReferencesFormAction();

            return $formActions;
        }

        $formActions[] = [
            'type'    => 'Button',
            'caption' => 'Update',
            'onClick' => 'IPS_RequestAction($id, "UpdateCertificate", "");',
        ];

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Expert area',
            'expanded'  => false,
            'items'     => [
                $this->GetInstallVarProfilesFormItem(),
            ],
        ];

        $formActions[] = $this->GetInformationFormAction();
        $formActions[] = $this->GetReferencesFormAction();

        return $formActions;
    }

    private function SetUpdateInterval(int $min = null)
    {
        if (is_null($min)) {
            $min = $this->ReadPropertyInteger('update_interval');
        }
        $this->MaintainTimer('UpdateCertificate', $min * 60 * 1000);
    }

    private function UpdateCertificate()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $certificate = $this->download_certificate();
        if ($certificate === false) {
            return;
        }
        $privateKey = $this->download_privatekey();
        if ($privateKey === false) {
            return;
        }

        $cert_parsed = openssl_x509_parse($certificate, false);
        $this->SendDebug(__FUNCTION__, 'certificate=' . print_r($cert_parsed, true), 0);

        $check = openssl_x509_check_private_key($certificate, $privateKey);
        if ($check == false) {
            $this->SendDebug(__FUNCTION__, 'certificate and private key don\'t match', 0);
            return;
        }

        $validFrom_time_t = $cert_parsed['validFrom_time_t'];
        $validTo_time_t = $cert_parsed['validTo_time_t'];
        $this->SendDebug(__FUNCTION__, 'certificate is valid from ' . date('d.m.Y H:i', $validFrom_time_t) . ' ... until ' . date('d.m.Y H:i', $validTo_time_t), 0);

        $now = time();
        if ($validFrom_time_t > $now) {
            $this->SendDebug(__FUNCTION__, 'certificate is not yet valid', 0);
            return;
        }

        if ($validTo_time_t < $now) {
            $this->SendDebug(__FUNCTION__, 'certificate is not longer valid', 0);
            return;
        }

        $this->SetValue('ValidUntil', $validTo_time_t);

        $webserver_instID = $this->ReadPropertyInteger('webserver_instID');

        $certificate_b64 = base64_encode($certificate);
        $privateKey_b64 = base64_encode($privateKey);

        if (IPS_InstanceExists($webserver_instID) == false) {
            $this->SendDebug(__FUNCTION__, 'webserver instance is not given/valid', 0);
            return;
        }

        if (IPS_GetProperty($webserver_instID, 'Certificate') != $certificate_b64 || IPS_GetProperty($webserver_instID, 'PrivateKey') != $privateKey_b64) {
            IPS_SetProperty($webserver_instID, 'Certificate', $certificate_b64);
            IPS_SetProperty($webserver_instID, 'PrivateKey', $privateKey_b64);
            IPS_ApplyChanges($webserver_instID);
            $changed = true;
        } else {
            $changed = true;
        }

        $inst = IPS_GetInstance($webserver_instID);
        $webserver_status = $inst['InstanceStatus'];
        $this->SetValue('WebServerStatus', $webserver_status);
        $statusText = $this->GetValueFormatted('WebServerStatus');
        $this->SendDebug(__FUNCTION__, 'webserver status=' . $webserver_status . ' (' . $statusText . ')', 0);

        $script = $this->ReadPropertyString('script');
        if ($script != '') {
            $params = [
                'webServer_instID'     => $webserver_instID,
                'webServer_status'     => $webserver_status,
                'webServer_statusText' => statusText,
                'instanceID'           => $this->InstanceID,
                'validFrom'            => $validFrom_time_t,
                'validTo'              => $validTo_time_t,
                'changed'              => $changed,
            ];
            @$r = IPS_RunScriptTextWaitEx($script, $params);
            $this->SendDebug(__FUNCTION__, 'script("...", ' . print_r($params, true) . ' => ' . $r, 0);
        }

        $this->SetUpdateInterval();
    }

    private function LocalRequestAction($ident, $value)
    {
        $r = true;
        switch ($ident) {
            case 'UpdateCertificate':
                $this->UpdateCertificate();
                break;
            default:
                $r = false;
                break;
        }
        return $r;
    }

    public function RequestAction($ident, $value)
    {
        if ($this->LocalRequestAction($ident, $value)) {
            return;
        }
        if ($this->CommonRequestAction($ident, $value)) {
            return;
        }

        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $this->SendDebug(__FUNCTION__, 'ident=' . $ident . ', value=' . $value, 0);

        $r = false;
        switch ($ident) {
            default:
                $this->SendDebug(__FUNCTION__, 'invalid ident ' . $ident, 0);
                break;
        }
        if ($r) {
            $this->SetValue($ident, $value);
        }
    }

    private function do_HttpRequest($endpoint, $apikey)
    {
        $server = $this->ReadPropertyString('server');
        $port = $this->ReadPropertyInteger('port');

        $url = 'https://' . $server . ':' . $port . $endpoint;

        $header = [
            'X-API-KEY: ' . $apikey,
        ];

        $curl_opts = [
            CURLOPT_URL            => $url,
            CURLOPT_HTTPHEADER     => $header,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
        ];

        $this->SendDebug(__FUNCTION__, 'http: url=' . $url . ', mode=GET', 0);
        $this->SendDebug(__FUNCTION__, '  header=' . print_r($header, true), 0);

        $time_start = microtime(true);

        $ch = curl_init();
        curl_setopt_array($ch, $curl_opts);
        $response = curl_exec($ch);
        $cerrno = curl_errno($ch);
        $cerror = $cerrno ? curl_error($ch) : '';
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_info = curl_getinfo($ch);
        curl_close($ch);

        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, ' => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);
        $this->SendDebug(__FUNCTION__, '    response=' . $response, 0);

        $statuscode = 0;
        $err = '';
        $result = '';
        if ($cerrno) {
            $statuscode = self::$IS_SERVERERROR;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        } elseif ($httpcode != 200) {
            if ($httpcode == 403) {
                $statuscode = self::$IS_FORBIDDEN;
                $err = 'got http-code ' . $httpcode . ' (forbidden)';
            } elseif ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = self::$IS_SERVERERROR;
                $err = 'got http-code ' . $httpcode . ' (server error)';
            } else {
                $statuscode = self::$IS_HTTPERROR;
                $err = "got http-code $httpcode";
            }
        }

        if ($statuscode) {
            $this->SendDebug(__FUNCTION__, ' => statuscode=' . $statuscode . ', err=' . $err, 0);
            $this->MaintainStatus($statuscode);
            return false;
        }

        $this->MaintainStatus(IS_ACTIVE);
        return $response;
    }

    private function download_certificate()
    {
        $cert_name = $this->ReadPropertyString('cert_name');
        $cert_apikey = $this->ReadPropertyString('cert_apikey');

        $endpoint = '/certwarden/api/v1/download/certificates/' . $cert_name;

        $certificate = $this->do_HttpRequest($endpoint, $cert_apikey);
        return $certificate;
    }

    private function download_privatekey()
    {
        $key_name = $this->ReadPropertyString('key_name');
        $key_apikey = $this->ReadPropertyString('key_apikey');

        $endpoint = '/certwarden/api/v1/download/privatekeys/' . $key_name;

        $privatekey = $this->do_HttpRequest($endpoint, $key_apikey);
        return $privatekey;
    }
}
