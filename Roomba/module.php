<?
// Klassendefinition
class Roomba extends IPSModule {

	private $insId = 0;

	// Der Konstruktor des Moduls
	// Überschreibt den Standard Kontruktor von IPS
	public function __construct($InstanceID) {
		// Diese Zeile nicht löschen
		parent::__construct($InstanceID);
		// Selbsterstellter Code
		$this->insId = $InstanceID;
	}

	// Überschreibt die interne IPS_Create($id) Funktion
	public function Create() {
		// Diese Zeile nicht löschen.
		parent::Create();

		$this->RequireParent('{EE0D345A-CF31-428A-A613-33CE98E752DD}');    //MQTT Client

		//These lines are parsed on Symcon Startup or Instance creation
		//You cannot use variables here. Just static values.
		$this->RegisterPropertyInteger("TimeBetweenMission", 36);
		$this->RegisterPropertyInteger("PresenceVariable", 0);
	
		//Timer
		$this->RegisterTimer("UpdateTimer", 60000, 'ROOMBA_CheckStart($_IPS[\'TARGET\']);');

		//Variablenprofile
		//Bin
		if(!IPS_VariableProfileExists("ROOMBA.Bin")) {
			IPS_CreateVariableProfile("ROOMBA.Bin", 1);
			IPS_SetVariableProfileValues("ROOMBA.Bin", 0, 2, 1);
			IPS_SetVariableProfileIcon("ROOMBA.Bin", "Recycling");
			IPS_SetVariableProfileAssociation("ROOMBA.Bin", 0, $this->Translate("not available"), "", 0xC0C0C0);
			IPS_SetVariableProfileAssociation("ROOMBA.Bin", 1, $this->Translate("empty"), "", 0x00FF00);
			IPS_SetVariableProfileAssociation("ROOMBA.Bin", 2, $this->Translate("full"), "", 0xFF0000);
		}

		//MissionState
		if(!IPS_VariableProfileExists("ROOMBA.MissionState")) {
			IPS_CreateVariableProfile("ROOMBA.MissionState", 1);
			IPS_SetVariableProfileValues("ROOMBA.MissionState", 0, 4, 1);
			IPS_SetVariableProfileIcon("ROOMBA.MissionState", "Information");
			IPS_SetVariableProfileAssociation("ROOMBA.MissionState", 0, $this->Translate("unknown"), "", 0xC0C0C0);
			IPS_SetVariableProfileAssociation("ROOMBA.MissionState", 1, $this->Translate("stopped"), "", 0xFFFF00);
			IPS_SetVariableProfileAssociation("ROOMBA.MissionState", 2, $this->Translate("charging"), "", 0x00FF00);
			IPS_SetVariableProfileAssociation("ROOMBA.MissionState", 3, $this->Translate("running"), "", 0xFFFF00);
			IPS_SetVariableProfileAssociation("ROOMBA.MissionState", 4, $this->Translate("to base"), "", 0x0000FF);
		}

		//State
		if(!IPS_VariableProfileExists("ROOMBA.State")) {
			IPS_CreateVariableProfile("ROOMBA.State", 1);
			IPS_SetVariableProfileValues("ROOMBA.State", 0, 0, 0);
			IPS_SetVariableProfileIcon("ROOMBA.State", "Information");
			IPS_SetVariableProfileAssociation("ROOMBA.State", 0, $this->Translate("Ready"), "", 0x00FF00);
			IPS_SetVariableProfileAssociation("ROOMBA.State", 7, $this->Translate("No Bin"), "", 0xFFFF00);
			IPS_SetVariableProfileAssociation("ROOMBA.State", 8, $this->Translate("StandBy"), "", 0x00FF00);
		}

		//Control
		if(!IPS_VariableProfileExists("ROOMBA.Control")) {
			IPS_CreateVariableProfile("ROOMBA.Control", 1);
			IPS_SetVariableProfileValues("ROOMBA.Control", 0, 0, 0);
			IPS_SetVariableProfileIcon("ROOMBA.Control", "Script");
			IPS_SetVariableProfileAssociation("ROOMBA.Control", 1, $this->Translate("dock"), "", -1);
			IPS_SetVariableProfileAssociation("ROOMBA.Control", 2, $this->Translate("start"), "", -1);
			IPS_SetVariableProfileAssociation("ROOMBA.Control", 3, $this->Translate("stop"), "", -1);
			IPS_SetVariableProfileAssociation("ROOMBA.Control", 4, $this->Translate("pause"), "", -1);
			IPS_SetVariableProfileAssociation("ROOMBA.Control", 5, $this->Translate("resume"), "", -1);
		}

		//Lock
		if(!IPS_VariableProfileExists("ROOMBA.Lock")) {
			IPS_CreateVariableProfile("ROOMBA.Lock", 0);
			IPS_SetVariableProfileIcon("ROOMBA.Lock", "Lock");
			IPS_SetVariableProfileAssociation("ROOMBA.Lock", True, $this->Translate("unlocked"), "LockOpen", 0x00FF00);
			IPS_SetVariableProfileAssociation("ROOMBA.Lock", False, $this->Translate("locked"), "LockClosed", 0xFF0000);
		}
	
		$this->RegisterVariableInteger("BatPct", $this->Translate("Battery"), "~Battery.100");
		$this->RegisterVariableInteger("Bin", $this->Translate("Bin"), "ROOMBA.Bin");
		$this->RegisterVariableInteger("CleanMissionStatus", $this->Translate("Mission"), "ROOMBA.MissionState");
		$this->RegisterVariableInteger("State", $this->Translate("State"), "ROOMBA.State");

		$this->RegisterVariableInteger("Control", $this->Translate("Control"), "ROOMBA.Control");
		$this->EnableAction("Control");

		$lastAutostart = $this->RegisterVariableInteger("LastAutostart", $this->Translate("last Autostart"));
		$cbsVar = $this->RegisterVariableBoolean("CleanBySchedule", $this->Translate("Clean by Schedule"), "ROOMBA.Lock");

		IPS_SetHidden($lastAutostart, true);
		IPS_SetHidden($cbsVar, true);

		//Zeitplan erstellen
		if(@IPS_GetEventIDByName($this->Translate("Schedule"), $this->insId) === false){
			$evt = IPS_CreateEvent(2);
			IPS_SetName($evt, $this->Translate("Schedule"));
			IPS_SetParent($evt, $this->insId);
			IPS_SetEventScheduleAction($evt, 1, $this->Translate("unlocked"), 0x00FF00, "SetValueBoolean($cbsVar, true);");
			IPS_SetEventScheduleAction($evt, 2, $this->Translate("locked"), 0xFF0000, "SetValueBoolean($cbsVar, false);");
			IPS_SetEventScheduleGroup($evt, 1, 1);
			IPS_SetEventScheduleGroup($evt, 2, 2);
			IPS_SetEventScheduleGroup($evt, 3, 4);
			IPS_SetEventScheduleGroup($evt, 4, 8);
			IPS_SetEventScheduleGroup($evt, 5, 16);
			IPS_SetEventScheduleGroup($evt, 6, 32);
			IPS_SetEventScheduleGroup($evt, 7, 64);
			IPS_SetEventScheduleGroupPoint($evt, 1, 1, 0, 0, 0, 2);
			IPS_SetEventScheduleGroupPoint($evt, 1, 2, 8, 0, 0, 1);
			IPS_SetEventScheduleGroupPoint($evt, 1, 3, 18, 0, 0, 2);
			IPS_SetEventScheduleGroupPoint($evt, 2, 1, 0, 0, 0, 2);
			IPS_SetEventScheduleGroupPoint($evt, 2, 2, 8, 0, 0, 1);
			IPS_SetEventScheduleGroupPoint($evt, 2, 3, 18, 0, 0, 2);
			IPS_SetEventScheduleGroupPoint($evt, 3, 1, 0, 0, 0, 2);
			IPS_SetEventScheduleGroupPoint($evt, 3, 2, 8, 0, 0, 1);
			IPS_SetEventScheduleGroupPoint($evt, 3, 3, 18, 0, 0, 2);
			IPS_SetEventScheduleGroupPoint($evt, 4, 1, 0, 0, 0, 2);
			IPS_SetEventScheduleGroupPoint($evt, 4, 2, 8, 0, 0, 1);
			IPS_SetEventScheduleGroupPoint($evt, 4, 3, 18, 0, 0, 2);
			IPS_SetEventScheduleGroupPoint($evt, 5, 1, 0, 0, 0, 2);
			IPS_SetEventScheduleGroupPoint($evt, 5, 2, 8, 0, 0, 1);
			IPS_SetEventScheduleGroupPoint($evt, 5, 3, 18, 0, 0, 2);
			IPS_SetEventScheduleGroupPoint($evt, 6, 1, 0, 0, 0, 2);
			IPS_SetEventScheduleGroupPoint($evt, 6, 2, 8, 0, 0, 1);
			IPS_SetEventScheduleGroupPoint($evt, 6, 3, 18, 0, 0, 2);
			IPS_SetEventScheduleGroupPoint($evt, 7, 1, 0, 0, 0, 2);
			IPS_SetEventScheduleGroupPoint($evt, 7, 2, 8, 0, 0, 1);
			IPS_SetEventScheduleGroupPoint($evt, 7, 3, 18, 0, 0, 2);
			IPS_SetEventActive($evt, true);
		}
	}

	// Überschreibt die intere IPS_ApplyChanges($id) Funktion
	public function ApplyChanges() {
		// Diese Zeile nicht löschen
		parent::ApplyChanges();
	}

	public function RequestAction($Ident, $Value) {
		switch($Ident) {
			case "Control":
				switch($Value) {
					case 1:	//Dock
						$this->Dock();
						break;

					case 2: //Start
						$this->Start();
						break;

					case 3: //Stop
						$this->Stop();
						break;
					
					case 4: //Pause
						$this->Pause();
						break;

					case 5: //Resume
						$this->Resume();
						break;
				}
				break;
				
			default:
				throw new Exception($this->Translate("Invalid Ident"));
		}
	}

	public function GetConfigurationForm(){
		return '{
			"elements":
			[
				{ "type": "Label", "label": "TimeBetweenMission" },
				{ "type": "IntervalBox", "name": "TimeBetweenMission", "caption": "Hours" },
				{ "type": "Label", "label": "" },
				{ "type": "SelectVariable", "name": "PresenceVariable", "caption": "PresenceVariable" }
			]
		}';
	}

	public function ReceiveData($JSONString){
		$data = json_decode($JSONString);
		$jsonData = utf8_decode($data->Buffer);

		$this->SendDebug(__FUNCTION__, print_r($jsonData, true), 0);

		if($jsonData->SENDER !== 'MQTT_GET_PAYLOAD') return; //Just process payload

		$payload = json_decode($jsonData->Payload);

		if($payload->state){
			if($payload->state->reported){
				$reported = $payload->state->reported;

				if($reported->batPct) SetValueInteger($this->GetIDForIdent("BatPct"), $reported->batPct);

				if($reported->bin){
					if($reported->bin->present){
						if($reported->bin->full){
							SetValueInteger($this->GetIDForIdent("Bin"), 2);
						}else{
							SetValueInteger($this->GetIDForIdent("Bin"), 1);
						}
					}else{
						SetValueInteger($this->GetIDForIdent("Bin"), 0);
					}
				}

				if($reported->cleanMissionStatus){
					$missionState = $reported->cleanMissionStatus;
					switch($missionState->phase){
						case 'stop':
							SetValueInteger($this->GetIDForIdent("CleanMissionStatus"), 1);
							break;
						case 'charge':
							SetValueInteger($this->GetIDForIdent("CleanMissionStatus"), 2);
							break;
						case 'run':
							SetValueInteger($this->GetIDForIdent("CleanMissionStatus"), 3);
							break;
						case 'hmUsrDock':
						case 'hmPostMsn':
							SetValueInteger($this->GetIDForIdent("CleanMissionStatus"), 4);
							break;
						default:
							SetValueInteger($this->GetIDForIdent("CleanMissionStatus"), 0);
							break;
					}

					SetValueInteger($this->GetIDForIdent("State"), $missionState->notReady);
				}
			}
		}
	}

	public function CheckStart() {
		try{
			$presence = false;
			$presenceId = $this->ReadPropertyInteger('PresenceVariable');
			if($presenceId !== 0 && IPS_VariableExists($presenceId)){
				$presence = GetValueBoolean($presenceId);
			}

			//Abwesend & freigabe zur Reinigung & Roomba ist bereit & Reinigung läuft noch nicht & letzte Reinigung ist min 12 Std. her
			if(!$presence AND
				GetValueBoolean($this->GetIDForIdent('CleanBySchedule')) AND
				GetValueInteger($this->GetIDForIdent('State')) == 0 AND
				(GetValueInteger($this->GetIDForIdent('CleanMissionStatus')) == 1 OR GetValueInteger($this->GetIDForIdent('CleanMissionStatus')) == 2) AND
				(GetValueInteger($this->GetIDForIdent('LastAutostart')) + ($this->ReadPropertyInteger('TimeBetweenMission') * 3600)) < time()){
				//Zeit zwischen Reinigung min. Stunden x 3600 Sek Sek
		
				$this->Start();
				SetValueInteger($this->GetIDForIdent('LastAutostart'), time());
			}
		}finally{
			if(GetValueInteger($this->GetIDForIdent('Control')) > 0) SetValueInteger($this->GetIDForIdent("Control"), 0);
		}
	}
	
	private function _apiCall ($topic, $command) {
		$cmd = [
				'command' => $command, 
				'time' => time(), 
				'initiator' => 'localApp'
				];
	    if ($topic === 'delta') {
			$cmd = [
					'state' => $command
					];
	    }
		
		$jsonPublish = [
			'Topic' 	=> $topic,
			'MSG'		=> json_encode($cmd),
			'Retain'	=> 0
		];

		$json = json_encode([
			'DataID' => '{97475B04-67C3-A74D-C970-E9409B0EFA1D}', //MQTT Client
            'Buffer' => utf8_encode($jsonPublish)]);
        if ($this->HasActiveParent()) {
            $res = parent::SendDataToParent($json);
        } else {
            $this->SendDebug(__FUNCTION__, 'No active Parent', 0);
        }
	}

	public function Dock() {
		try{
			$this->_apiCall('cmd', 'dock');
		}finally{
			SetValueInteger($this->GetIDForIdent("Control"), 0);
		}
	}
	
	public function Start() {
		try{
			$this->_apiCall('cmd', 'start');
		}finally{
			SetValueInteger($this->GetIDForIdent("Control"), 0);
		}
	}
	
	public function Stop() {
		try{
			$this->_apiCall('cmd', 'stop');
		}finally{
			SetValueInteger($this->GetIDForIdent("Control"), 0);
		}
	}
	
	public function Pause() {
		try{
			$this->_apiCall('cmd', 'pause');
		}finally{
			SetValueInteger($this->GetIDForIdent("Control"), 0);
		}
	}
	
	public function Resume() {
		try{
			$this->_apiCall('cmd', 'resume');
		}finally{
			SetValueInteger($this->GetIDForIdent("Control"), 0);
		}
	}
}
