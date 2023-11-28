<?php

declare(strict_types=1);

class IPSArylic extends IPSModule
{

	// -------------------------------------------------------------------------
	public function Create()
	{
		//Never delete this line!
		parent::Create();

		$this->RegisterPropertyString("IPAddress", "");
		$this->RegisterPropertyInteger ('RefreshTimer', 5);
		$this->RegisterPropertyInteger ('ConfigTimer', 300);
	
		$this->RegisterTimer('RefreshTimer',  0, 'ARY_RefreshValues($_IPS[\'TARGET\']);');
		$this->RegisterTimer('ConfigTimer',  0, 'ARY_RefreshConfig($_IPS[\'TARGET\']);');
	}

	// -------------------------------------------------------------------------
	public function Destroy()
	{
		//Never delete this line!
		parent::Destroy();
	}

	// -------------------------------------------------------------------------
	public function ApplyChanges()
	{
		//Never delete this line!
		parent::ApplyChanges();

		$this->RegisterProfileInteger("Volume.ARYLIC",   "Intensity",   "", " %",    0, 100, 1);
		$this->RegisterProfileIntegerEx("Status.ARYLIC", "Information", "", "",   Array( 
			Array(0, "prev",       "", -1),
			Array(1, "play",       "", -1),
			Array(2, "pause",      "", -1),
			Array(3, "stop",       "", -1),
			Array(4, "next",       "", -1),
			Array(5, "load",       "", -1) ));
		$this->RegisterProfileIntegerEx("Preset.ARYLIC.".$this->InstanceID, "Information", "", "",   Array( 
			Array(1, "Preset 1",   "", -1),
			Array(2, "Preset 2",   "", -1),
			Array(3, "Preset 3",   "", -1),
			Array(4, "Preset 4",   "", -1),
			Array(5, "Preset 5",   "", -1),
			Array(6, "Preset 6",   "", -1),
			Array(7, "Preset 7",   "", -1),
			Array(8, "Preset 8",   "", -1),
			Array(9, "Preset 9",   "", -1),
			Array(10, "Preset 10",   "", -1)));
		$this->RegisterProfileIntegerEx("Switch.ARYLIC", "Speaker", "",   "", Array( 
			Array(0, "Off", "", 0xFF0000),
			Array(1, "On",  "", 0x00FF00) ));


		$this->RegisterVariableInteger("Status", "Status", "Status.ARYLIC", 10);
		$this->EnableAction("Status");
		$this->RegisterVariableInteger("Volume", "Volume", "Volume.ARYLIC", 20);
		$this->EnableAction("Volume");
		$this->RegisterVariableInteger("Mute","Mute", "Switch.ARYLIC", 30);
        $this->EnableAction("Mute");
		$this->RegisterVariableInteger("Preset","Preset", "Preset.ARYLIC.".$this->InstanceID, 40);
        $this->EnableAction("Preset");

		$this->RegisterVariableString("Title", "Title", "", 100);
		$this->RegisterVariableString("Artist", "Artist", "", 110);
		$this->RegisterVariableString("Album", "Album", "", 120);

		$this->RegisterVariableString("PlayingUri", "PlayingUri", "", 200);
		$this->RegisterVariableString("AlbumUri", "AlbumUri", "", 210);

		$this->SetTimerInterval('RefreshTimer', $this->ReadPropertyInteger('RefreshTimer') * 1000);
		$this->SetTimerInterval('ConfigTimer', $this->ReadPropertyInteger('ConfigTimer') * 1000);

	}

	// -------------------------------------------------------------------------
	public function RequestAction($Ident, $Value)
    {
        switch($Ident) {
			case "Volume":
                $this->SetVolume($Value);
                break;
			case "Mute":
				$this->SetMute($Value);
				break;
			case "Preset":
				$this->SetValue('Preset', $Value);
				$this->SetPreset($Value);
				break;
			case "Status":
                switch($Value) {
                    case 0: //Prev
                        $this->Previous();
                        break;
                    case 1: //Play
                        $this->Play();
                        break;
                    case 2: //Pause
                        $this->Pause();
                        break;
                    case 3: //Stop
                        $this->Stop();
                        break;
                    case 4: //Next
                        $this->Next();
                        break;
                }
                break;
            default:
                throw new Exception("Invalid ident");
        }
		$this->RefreshValues();
	}

	// -------------------------------------------------------------------------
	protected function SetStatusValue($ident, $value) {
		if ($this->GetValue($ident) != $value) {
			$this->SetValue($ident, $value);
		}
	}

	
	// -------------------------------------------------------------------------
	protected function sendAPIRequest($command, $value)
	{
		$ipSetting  = $this->ReadPropertyString("IPAddress");
		$requestUrl = "http://$ipSetting/httpapi.asp?command=$command"; 
		if ($value != null) {
			$requestUrl .= $value;
		}
		$content = Sys_GetURLContent($requestUrl);
	}

	// -------------------------------------------------------------------------
    protected function hexToStr($hex)
	{
        if ($hex == 'unknown') {
            return '';
        }
        try {
            $string='';
            for ($i=0; $i < strlen($hex)-1; $i+=2){
                $string .= @chr(hexdec($hex[$i].$hex[$i+1]));
            }
            return $string;
        } catch (Throwable $e) {
           return $e->getMessage();
        }
	}


	// -------------------------------------------------------------------------
	protected function executeSoapRequest($sub, $srv, $act, $msg) {
		/*
		Example for SOAP Requests found at the following page:
		https://forum.arylic.com/t/http-api-problems/2110
		*/

    	$ipSetting  = $this->ReadPropertyString("IPAddress");
	    $url='http://'.$ipSetting.':59152/upnp/control/'.$sub;
        $envs='<s:Envelope s:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/" xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body>';
        $enve='</s:Body></s:Envelope>';
        $dat=$envs.'<u:'.$act.' xmlns:u="'.$srv.'">'.$msg.'</u:'.$act.'>'.$enve;
        $hsa='SOAPACTION: "'.$srv.'#'.$act.'"';

        $headers = array( "Content-type: text/xml;charset=\"utf-8\"",
                          "Accept: text/xml",
                          "Cache-Control: no-cache",
                          "Pragma: no-cache",
                          $hsa, 
                          "Content-length: ".strlen($dat));
        $curl_handle=curl_init();
        curl_setopt($curl_handle, CURLOPT_HEADER, false);
        curl_setopt($curl_handle, CURLOPT_POST, true);
        curl_setopt($curl_handle, CURLOPT_POSTFIELDS, $dat); // the SOAP request
        curl_setopt($curl_handle, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl_handle, CURLOPT_URL,  $url);
        curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curl_handle, CURLOPT_TIMEOUT, 20);
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER,true);
        curl_setopt($curl_handle, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl_handle, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl_handle, CURLOPT_FAILONERROR, true);
        $soap_response = curl_exec($curl_handle);
        curl_close($curl_handle);

        return $soap_response;
    }

	// -------------------------------------------------------------------------
	protected function syncMeta() {
        $soap_response = $this->executeSoapRequest('rendertransport1', 'urn:schemas-upnp-org:service:AVTransport:1', 'GetMediaInfo', '<InstanceID>0</InstanceID>');
 
        /*
		Example:
        <s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" 
                        s:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
        <s:Body>
            <u:GetMediaInfoResponse xmlns:u="urn:schemas-upnp-org:service:AVTransport:1">
            <NrTracks>1</NrTracks>
            <MediaDuration>00:00:00</MediaDuration>
            <CurrentURI>https://orf-live.ors-shoutcast.at/oe3-q2a.m3u</CurrentURI>
            <CurrentURIMetaData>&lt;?xml version=&quot;1.0&quot; encoding=&quot;UTF-8&quot;?&gt;&lt;DIDL-Lite xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot; xmlns:upnp=&quot;urn:schemas-upnp-org:metadata-1-0/upnp/&quot; xmlns:song=&quot;www.wiimu.com/song/&quot; xmlns:custom=&quot;www.wiimu.com/custom/&quot; xmlns=&quot;urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/&quot;&gt;&lt;upnp:class&gt;object.item.audioItem.musicTrack&lt;/upnp:class&gt;&lt;item&gt;&lt;song:id&gt;0&lt;/song:id&gt;&lt;song:albumid&gt;&lt;/song:albumid&gt;&lt;song:singerid&gt;&lt;/song:singerid&gt;&lt;dc:title&gt;Ö3&lt;/dc:title&gt;&lt;upnp:artist&gt;EPIX&lt;/upnp:artist&gt;&lt;upnp:album&gt;Pop&lt;/upnp:album&gt;&lt;res&gt;https://orf-live.ors-shoutcast.at/oe3-q2a.m3u&lt;/res&gt;&lt;upnp:albumArtURI&gt;https://files.orf.at/vietnam2/files/oe3/202229/925500_fh_oe3_logo_925500.png&lt;/upnp:albumArtURI&gt;&lt;/item&gt;&lt;/DIDL-Lite&gt;</CurrentURIMetaData>
            <NextURI></NextURI>
            <NextURIMetaData></NextURIMetaData>
            <TrackSource>Personal Radio</TrackSource>
            <PlayMedium>RADIO-NETWORK</PlayMedium>
            <RecordMedium>NOT_IMPLEMENTED</RecordMedium>
            <WriteStatus>NOT_IMPLEMENTED</WriteStatus>
            </u:GetMediaInfoResponse>
        </s:Body> 
        </s:Envelope>
        */

        $xml = simplexml_load_string($soap_response);
        $playingURI = (string)$xml->xpath('//CurrentURI')[0];
		//echo $playingURI.PHP_EOL;
		$this->SetStatusValue('PlayingUri', $playingURI);
 
        $currentMeta = (string)$xml->xpath('//CurrentURIMetaData')[0];
        //echo $currentMeta.PHP_EOL;

        /* 
		Example:
		<?xml version="1.0" encoding="UTF-8"?>
        <DIDL-Lite xmlns:dc="http://purl.org/dc/elements/1.1/" 
                    xmlns:upnp="urn:schemas-upnp-org:metadata-1-0/upnp/" 
                    xmlns:song="www.wiimu.com/song/" 
                    xmlns:custom="www.wiimu.com/custom/" 
                    xmlns="urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/">
            <upnp:class>object.item.audioItem.musicTrack</upnp:class>
            <item>
                <song:id>0</song:id>
                <song:albumid></song:albumid>
                <song:singerid></song:singerid>
                <dc:title>Ö3</dc:title>
                <upnp:artist>EPIX</upnp:artist>
                <upnp:album>Pop</upnp:album>
                <res>https://orf-live.ors-shoutcast.at/oe3-q2a.m3u</res>
                <upnp:albumArtURI>https://files.orf.at/vietnam2/files/oe3/202229/925500_fh_oe3_logo_925500.png</upnp:albumArtURI>
            </item>
            </DIDL-Lite>
        */

        $xml = simplexml_load_string($currentMeta);
        $xml->registerXPathNamespace('meta', 'urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/');
        $albumUri =  (string)$xml->xpath('//upnp:albumArtURI')[0];
		$this->SetStatusValue('AlbumUri', $albumUri);	
        //echo $xml->xpath('//meta:res')[0].PHP_EOL;
    }

	// -------------------------------------------------------------------------
	protected function syncPresets() {
        $soap_response = $this->executeSoapRequest('PlayQueue1', 'urn:schemas-wiimu-com:service:PlayQueue:1', 'GetKeyMapping', '');

        /*
		Example:
		<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" s:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
          <s:Body>
            <u:GetKeyMappingResponse xmlns:u="urn:schemas-wiimu-com:service:PlayQueue:1">
              <QueueContext>&lt;?xml version=&quot;1.0&quot;?&gt;
              </QueueContext>
            </u:GetKeyMappingResponse>
          </s:Body> 
        </s:Envelope>
        */

        $xml = simplexml_load_string($soap_response);
        $mappingContent = (string)$xml->xpath('//QueueContext')[0];
		//echo $mappingContent->asXML().PHP_EOL;

        /*
 		Example:
        <?xml version="1.0"?>
        <KeyList>
          <ListName>KeyMappingQueue</ListName>
          <MaxNumber>21</MaxNumber>
          <Key0></Key0>
          <Key1>
            <Name>Ö3_#~1700592720</Name>
            <Source>{&quot;cmd&quot;:&quot;PLAYLIST_BACKUP&quot;, &quot;status&quot;:&quot;ok&quot;}</Source>
            <PicUrl>https://files.orf.at/vietnam2/files/oe3/202229/925500_fh_oe3_logo_925500.png</PicUrl>
          </Key1>
          <Key2>
            <Name>Classic Rock_#~1700592750</Name>
            <Source>{&quot;cmd&quot;:&quot;PLAYLIST_BACKUP&quot;, &quot;status&quot;:&quot;ok&quot;}</Source>
            <PicUrl>https://radio886.at/img/logo.svg</PicUrl>
          </Key2>
          ...
          <Key21></Key21>
        </KeyList>
        */

        $xml = simplexml_load_string($mappingContent);
        $xml->registerXPathNamespace('meta', 'urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/');
 
        for ($i = 1; $i <= 10; $i++) {
            $xmlName = $xml->xpath('//Key'.$i.'//Name');
            if (count($xmlName) > 0) {
                $name = explode('_#', (string)$xmlName[0])[0];
                //echo ''.$i.' --> '.$name.PHP_EOL;
				IPS_SetVariableProfileAssociation('Preset.ARYLIC.'.$this->InstanceID, $i, $name, '', -1);
            } else {
                //echo ''.$i.' --> '.PHP_EOL;
				IPS_SetVariableProfileAssociation('Preset.ARYLIC.'.$this->InstanceID, $i, 'Preset '.$i, '', -1);
            }
        }    
    }

	// -------------------------------------------------------------------------
	protected function syncStatus()
	{
		$ipSetting = $this->ReadPropertyString("IPAddress");
		$content = Sys_GetURLContent("http://$ipSetting/httpapi.asp?command=getPlayerStatus");
		$json = json_decode($content);

		$status = $json->status;
		if ($status == 'play') {
			$this->SetStatusValue('Status', 1);
		} else if ($status == 'pause') {
			$this->SetStatusValue('Status', 2);
		} else if ($status == 'stop') {
			$this->SetStatusValue('Status', 3);
		} else if ($status == 'load') {
			$this->SetStatusValue('Status', 5);
		} else {}

		$this->SetStatusValue('Volume', $json->vol);
		$this->SetStatusValue('Mute', $json->mute);

		$this->SetStatusValue('Title', $this->HexToStr($json->Title));
		$this->SetStatusValue('Artist', $this->HexToStr($json->Artist));
		$this->SetStatusValue('Album', $this->HexToStr($json->Album));
	}

	// -------------------------------------------------------------------------
	public function RefreshValues() {
		$this->syncStatus();
		$this->syncMeta();
	}

	// -------------------------------------------------------------------------
	public function RefreshConfig() {
		$this->syncPresets();
	}
	
	// -------------------------------------------------------------------------
	public function SetVolume($value) {
		$this->sendAPIRequest('setPlayerCmd:vol:', $value);
	}

	// -------------------------------------------------------------------------
	public function SetMute($value) {
		$this->sendAPIRequest('setPlayerCmd:mute:', $value==true ? '1' : '0');
	}

	// -------------------------------------------------------------------------
	public function SetPreset($value) {
		$this->sendAPIRequest('MCUKeyShortClick:', $value);
	}

	// -------------------------------------------------------------------------
	public function Previous() {
		$this->sendAPIRequest('setPlayerCmd:', 'prev');
	}

	// -------------------------------------------------------------------------
	public function Play() {
        if ($this->GetValue('PlayingUri') == '') {
            $this->SetPreset(1);
        } else {
            $this->sendAPIRequest('setPlayerCmd:', 'play');
        }
	}
	
	// -------------------------------------------------------------------------
	public function Stop() {
		$this->sendAPIRequest('setPlayerCmd:', 'stop');
	}

	// -------------------------------------------------------------------------
	public function Pause() {
		$this->sendAPIRequest('setPlayerCmd:', 'onepause');
	}

	// -------------------------------------------------------------------------
	public function Next() {
		$this->sendAPIRequest('setPlayerCmd:', 'next');
	}

	// -------------------------------------------------------------------------
	protected function RegisterProfileInteger($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize) {
        
        if(!IPS_VariableProfileExists($Name)) {
            IPS_CreateVariableProfile($Name, 1);
        } else {
            $profile = IPS_GetVariableProfile($Name);
            if($profile['ProfileType'] != 1)
            throw new Exception("Variable profile type does not match for profile ".$Name);
        }
        
        IPS_SetVariableProfileIcon($Name, $Icon);
        IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
        IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);
    }
    
 	// -------------------------------------------------------------------------
	protected function RegisterProfileIntegerEx($Name, $Icon, $Prefix, $Suffix, $Associations) {
        if ( sizeof($Associations) === 0 ){
            $MinValue = 0;
            $MaxValue = 0;
        } else {
            $MinValue = $Associations[0][0];
            $MaxValue = $Associations[sizeof($Associations)-1][0];
        }
        
        $this->RegisterProfileInteger($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, 0);
        
        foreach($Associations as $Association) {
            IPS_SetVariableProfileAssociation($Name, $Association[0], $Association[1], $Association[2], $Association[3]);
        }     
    }
}


