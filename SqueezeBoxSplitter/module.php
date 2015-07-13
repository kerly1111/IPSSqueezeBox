<?

class LMSSplitter extends IPSModule
{

    public function __construct($InstanceID)
    {

        //Never delete this line!
        parent::__construct($InstanceID);
        //These lines are parsed on Symcon Startup or Instance creation
        //You cannot use variables here. Just static values.
        $this->RegisterPropertyString("Host", "");
        $this->RegisterPropertyBoolean("Open", true);
        $this->RegisterPropertyInteger("Port", 9090);
        $this->RegisterPropertyInteger("Webport", 9000);
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        $change = false;
        $this->RequireParent("{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}");
        $ParentID = $this->GetParent();
        if (!($ParentID === false))
        {
            if (IPS_GetProperty($ParentID, 'Host') <> $this->ReadPropertyString('Host'))
            {
                IPS_SetProperty($ParentID, 'Host', $this->ReadPropertyString('Host'));
                $change = true;
            }
            if (IPS_GetProperty($ParentID, 'Port') <> $this->ReadPropertyInteger('Port'))
            {
                IPS_SetProperty($ParentID, 'Port', $this->ReadPropertyInteger('Port'));
                $change = true;
            }
            if (IPS_GetProperty($ParentID, 'Open') <> $this->ReadPropertyBoolean('Open'))
            {
                IPS_SetProperty($ParentID, 'Open', $this->ReadPropertyBoolean('Open'));
                $change = true;
            }

            if ($change)
                @IPS_ApplyChanges($ParentID);
        }
        $this->RegisterVariableString("BufferIN", "BufferIN");
        $this->RegisterVariableString("BufferOUT", "BufferOUT");
        $this->RegisterVariableBoolean("WaitForResponse", "WaitForResponse");
        $this->SendDataToParent("listen 1");
    }

################## PRIVATE     

    private function encode($raw)
    {
        $array = explode(' ', $raw); // Antwortstring in Array umwandeln
        $Data = new stdClass();
        $array[0] = urldecode($array[0]);
        $Data->MAC = $this->GetMAC($array[0]); // MAC in lesbares Format umwandeln
        $Data->Payload = $array;
        return $Data;
    }

    private function GetMAC($mac)
    {
        return $this->MAC = strtolower(str_replace(array("-", ":"), "", $mac));
    }

    private function lock($ident)
    {
        for ($i = 0; $i < 100; $i++)
        {
            if (IPS_SemaphoreEnter("LMS_" . (string) $this->InstanceID . (string) $ident, 1))
            {
                return true;
            }
            else
            {
                IPS_Sleep(mt_rand(1, 5));
            }
        }
        return false;
    }

    private function unlock($ident)
    {
        if (!(IPS_SemaphoreEnter("LMS_" . (string) $this->InstanceID . (string) $ident, 1)))
        {
            IPS_SemaphoreLeave("LMS_" . (string) $this->InstanceID . (string) $ident);
        }
    }

    private function SetWaitForResponse($Data)
    {
        if ($this->lock('BufferOut'))
        {
            $buffer = $this->GetIDForIdent('BufferOUT');
            $WaitForResponse = $this->GetIDForIdent('WaitForResponse');
            SetValueString($buffer, $Data);
            SetValueBoolean($WaitForResponse, true);
            $this->unlock('BufferOut');
            return true;
        }
        return false;
    }

    private function WaitForResponse()
    {
        $Event = $this->GetIDForIdent('WaitForResponse');
        for ($i = 0; $i < 5000; $i++)
        {
            if (GetValueBoolean($Event))
                IPS_Sleep(mt_rand(1, 5));
            else
            {
                if ($this->lock('BufferOut'))
                {
                    $buffer = $this->GetIDForIdent('BufferOUT');
                    $ret = GetValueString($buffer);
                    //IPS_LogMessage('FOUND RESPONSE', $ret);
                    SetValueString($buffer, "");
                    $this->unlock('BufferOut');
                    return $ret;
                }
                return false;
            }
        }
        return false;
    }

    private function ResetWaitForResponse()
    {
        if ($this->lock('BufferOut'))
        {
            $buffer = $this->GetIDForIdent('BufferOUT');
            $WaitForResponse = $this->GetIDForIdent('WaitForResponse');
            SetValueString($buffer, '');
            SetValueBoolean($WaitForResponse, false);
            $this->unlock('BufferOut');
            return true;
        }
        return false;
    }

    private function WriteResponse($Data)
    {
        $Event = $this->GetIDForIdent('WaitForResponse');
        if (!GetValueBoolean($Event))
            return false;
        $buffer = $this->GetIDForIdent('BufferOUT');
        $Data[0] = urldecode($Data[0]);
        $Data = implode(" ", $Data);
        if (!(strpos($Data, GetValueString($buffer)) === false))
        {
            if ($this->lock('BufferOut'))
            {
                $Event = $this->GetIDForIdent('WaitForResponse');
                SetValueString($buffer, $Data);
                SetValueBoolean($Event, false);
                $this->unlock('BufferOut');
                return true;
            }
            return 'Error on write ResponseBuffer';
        }
        return false;
    }

################## PUBLIC
    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:
     */

    public function SendEx($Text)
    {
        return $this->SendDataToParent($Text);
    }

    public function Rescan()
    {
        return $this->SendDataToParent('rescan');
    }

    public function Test1()
    {
        return "Test";
    }

    public function Test2()
    {
        return json_encode(array(1, 5, 7.9, 'footo' => 2, 'foo' => 'bar'));
    }

    public function CreateAllPlayer()
    {
        $players = $this->SendDataToParent('player count ?');

        for ($i = 0; $i < $players; $i++)
        {
            $player = $this->SendDataToParent('player id $i');
            $playerName = $this->SendDataToParent('player name '.$i);
            // Daten zerlegen und Childs anlegen/prüfen
            IPS_LogMessage('PLAYER ID' . $i, print_r($player, 1));
            IPS_LogMessage('PLAYER NAME' . $i, print_r($playerName, 1));
        }
    }

    public function GetPlayerInfo($Value)
    {
        return $this->SendDataToParent('players ' . $Value . ' 1');
    }

    public function GetLibaryInfo()
    {
        $gernes = $this->SendDataToParent('info total genres ?');
        $artists = $this->SendDataToParent('info total artists ?');
        $albums = $this->SendDataToParent('info total albums ?');
        $songs = $this->SendDataToParent('info total songs ?');
        return array('Geners'=>$gernes,'Artists'=>$artists,'Albums'=>$albums,'Songs'=>$songs);
    }

    public function GetVersion()
    {
        return $this->SendDataToParent('version ?');
    }

################## DataPoints

    public function ForwardData($JSONString)
    {
        //EDD ankommend von Device
        $data = json_decode($JSONString);
        IPS_LogMessage("IOSplitter FRWD MAC", $data->MAC);
        IPS_LogMessage("IOSplitter FRWD Payload", $data->Payload);
        $sendData = implode(":", $mac = str_split($data->MAC, 2)) . " " . $data->Payload;
        // Daten annehmen und mit MAC codieren. Senden an Parent
        //weiter zu IO  mit Warteschlange 
        $ret = $this->SendDataToParent($sendData);
        return $ret;
    }

    public function ReceiveData($JSONString)
    {
        // 018EF6B5-AB94-40C6-AA53-46943E824ACF ankommend von IO
        $data = json_decode($JSONString);
        IPS_LogMessage("IOSplitter RECV", utf8_decode($data->Buffer));
        $bufferID = $this->GetIDForIdent("BufferIN");
        if (!$this->lock("bufferin"))
        {
            throw new Exception("ReceiveBuffer is locked");
        }
        $head = GetValueString($bufferID);
        SetValueString($bufferID, '');
        $packet = explode(chr(0x0d), $head . $data->Buffer);
        $tail = array_pop($packet);
        SetValueString($bufferID, $tail);
        $this->unlock("bufferin");
        foreach ($packet as $part)
        {
            $encoded = $this->encode($part);
            $isResponse = $this->WriteResponse($encoded->Payload);
            if ($isResponse === true)
            {
                IPS_LogMessage("IOSplitter isResonse", "TRUE");
                // wird von Anfrage-Thread bearbeitet, für uns ist hier schluß
                continue;
            }
            elseif ($isResponse === false)
            {
                IPS_LogMessage("IOSplitter isResonse", "FALSE");
                if ($encoded->MAC <> "listen")
                {
                    $ret = $this->SendDataToChildren(json_encode(Array("DataID" => "{CB5950B3-593C-4126-9F0F-8655A3944419}", "MAC" => $encoded->MAC, "Payload" => $encoded->Payload)));
                    IPS_LogMessage("IOSplitter ReturnValue", print_r($ret, 1));
                }
            }
            else
            {
                throw new Exception($isResponse);
            }
        }
    }

    protected function SendDataToParent($Data)
    {
        //Semaphore setzen
        if (!$this->lock("ToParent"))
        {
            throw new Exception("Can not send to LMS");
        }
        // Anfrage an LMS
        if (substr($Data, -1) == '?')
            $WaitData = substr($Data, 0, -2);
        else // Befehl an LMS
            $WaitData = $Data;
        // Anfrage für die Warteschleife schreiben
        if (!$this->SetWaitForResponse($WaitData))
        {
            $this->unlock("ToParent");
            throw new Exception("Can not send to LMS");
        }
        // Daten senden
        $ret = @IPS_SendDataToParent($this->InstanceID, json_encode(Array("DataID" => "{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}", "Buffer" => $Data . chr(0x0d))));
        if ($ret === false)
        { // Senden fehlgeschlagen kein Response möglich
            //  Daten in Warteschleife löschen
            $this->ResetWaitForResponse();
            // SendeLock  velassen
            $this->unlock("ToParent");
            // Fehler
            throw new Exception("LMS not reachable");
        }
        else // erfolgreich gesendet
        {
            // Auf Antwort warten....
            $ret = $this->WaitForResponse();
            // SendeLock  velassen
            $this->unlock("ToParent");
            if ($ret === false) // Warteschleife lief in Timeout
            {
                throw new Exception("No answer from LMS");
            }
            // Rückgabe ist eine Bestätigung von einem Befehl
            if ($Data == $ret)
            {
                IPS_LogMessage('FOUND RESPONSE1', print_r($ret, 1));
                return true;
            }
            // Rückgabe ist ein Wert auf eine Anfrage, abschneiden der Anfrage.
            $ret = str_replace($WaitData, "", $ret);
            IPS_LogMessage('FOUND RESPONSE2', print_r($ret, 1));
            return $ret;
        }
    }

    protected function SendDataToChildren($Data)
    {
        return IPS_SendDataToChildren($this->InstanceID, $Data);
    }

################## DUMMYS / WOARKAROUNDS - protected

    protected function GetParent()
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //          
        $instance = IPS_GetInstance($this->InstanceID);
        return ($instance['ConnectionID'] > 0) ? $instance['ConnectionID'] : false;
    }

    protected function HasActiveParent($ParentID)
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //          
        if ($ParentID > 0)
        {
            $parent = IPS_GetInstance($ParentID);
            if ($parent['InstanceStatus

             

              

              

             

             '] == 102)
                return true;
        }
        return false;
    }

    protected function SetStatus($data)
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //           
    }

    protected function RegisterTimer($data, $cata)
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //           
    }

    protected function SetTimerInterval($data, $cata)
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //           
    }

    protected function LogMessage($data, $cata)
    {
        
    }

    protected function SetSummary($data)
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__ . "Data:" . $data); //                   
    }

}

?>