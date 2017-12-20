<?
require_once('roomba.php');

// Klassendefinition
class Roomba extends IPSModule {

	private $roomba = NULL;
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

		//These lines are parsed on Symcon Startup or Instance creation
		//You cannot use variables here. Just static values.
		$this->RegisterPropertyString("Address", "");
		$this->RegisterPropertyString("Username", "");
		$this->RegisterPropertyString("Password", "");

		$this->RegisterPropertyBoolean("AutomaticUpdate", False);
		$this->RegisterPropertyInteger("UpdateInterval", 5);
		$this->RegisterPropertyInteger("TimeBetweenMission", 36);

		$this->RegisterPropertyString("PresenceVariable", "");
	
		//Timer
		$this->RegisterTimer("UpdateTimer", 0, 'ROOMBA_Update($_IPS[\'TARGET\']);');
	
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

		if($this->ReadPropertyBoolean("AutomaticUpdate")){
			$this->SetTimerInterval("UpdateTimer", $this->ReadPropertyInteger("UpdateInterval") * 60000);
		}else{
			$this->SetTimerInterval("UpdateTimer", 0);
		}
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
		if($this->ReadPropertyBoolean('AutomaticUpdate')){
			return '{
				"elements":
				[
					{ "type": "ValidationTextBox", "name": "Address", "caption": "Address" },
					{ "type": "ValidationTextBox", "name": "Username", "caption": "Username" },
					{ "type": "ValidationTextBox", "name": "Password", "caption": "Password" },
					{ "type": "Label", "label": "" },
					{ "type": "CheckBox", "name": "AutomaticUpdate", "caption": "Automatic Update" },
					{ "type": "IntervalBox", "name": "UpdateInterval", "caption": "Minutes" },
					{ "type": "Label", "label": "" },
					{ "type": "Label", "label": "TimeBetweenMission" },
					{ "type": "IntervalBox", "name": "TimeBetweenMission", "caption": "Hours" },
					{ "type": "Label", "label": "" },
					{ "type": "SelectVariable", "name": "PresenceVariable", "caption": "PresenceVariable" }
				]
			}';
		}else{
			return '{
				"elements":
				[
					{ "type": "ValidationTextBox", "name": "Address", "caption": "Address" },
					{ "type": "ValidationTextBox", "name": "Username", "caption": "Username" },
					{ "type": "ValidationTextBox", "name": "Password", "caption": "Password" },
					{ "type": "Label", "label": "" },
					{ "type": "CheckBox", "name": "AutomaticUpdate", "caption": "Automatic Update" }
				]
			}';
		}
	}

	private function Connect($needValues) {
		$this->roomba = new RoombaConnector($this->ReadPropertyString('Address'), $this->ReadPropertyString('Username'), $this->ReadPropertyString('Password'), $needValues);
	}

	private function Disconnect(){
		$this->roomba->disconnect();
	}

	private function CheckMissionStatus(){
		if($this->roomba->ContainsValue('cleanMissionStatus')){
			switch($this->roomba->GetValue('cleanMissionStatus')->phase){
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
			SetValueInteger($this->GetIDForIdent("State"), $this->roomba->GetValue('cleanMissionStatus')->notReady);
		}
	}

	/**
	* Die folgenden Funktionen stehen automatisch zur Verfügung, wenn das Modul über die "Module Control" eingefügt wurden.
	* Die Funktionen werden, mit dem selbst eingerichteten Prefix, in PHP und JSON-RPC wiefolgt zur Verfügung gestellt:
	*
	* ABC_MeineErsteEigeneFunktion($id);
	*
	*/
	public function Update() {
		try{
			$this->Connect([
				'batPct',
				'bin',
				'cleanMissionStatus',
				'pose',
				'dock'
			]);

			$presence = false;
			$presenceId = $this->ReadPropertyString('PresenceVariable');
			if($presenceId !== "" && IPS_VariableExists($presenceId)){
				$presence = GetValueBoolean($presenceId);
			}

			//Abwesend & freigabe zur Reinigung & Roomba ist bereit & Reinigung läuft noch nicht & letzte Reinigung ist min 12 Std. her
			if(!$presence AND
				GetValueBoolean($this->GetIDForIdent('CleanBySchedule')) AND
				GetValueInteger($this->GetIDForIdent('State')) == 0 AND
				(GetValueInteger($this->GetIDForIdent('CleanMissionStatus')) == 1 OR GetValueInteger($this->GetIDForIdent('CleanMissionStatus')) == 2) AND
				(GetValueInteger($this->GetIDForIdent('LastAutostart')) + ($this->ReadPropertyInteger('TimeBetweenMission') * 3600)) < time()){
				//Zeit zwischen Reinigung min. Stunden x 3600 Sek Sek
		
				$roomba->Start();
				SetValueInteger($this->GetIDForIdent('LastAutostart'), time());
			}

			$this->roomba->loop();

			if($this->roomba->ContainsValue('batPct')){
				SetValueInteger($this->GetIDForIdent("BatPct"), $this->roomba->GetValue('batPct'));
			}
			
			if($this->roomba->ContainsValue('bin')){
				if($this->roomba->GetValue('bin')->present){
					if($this->roomba->GetValue('bin')->full){
						SetValueInteger($this->GetIDForIdent("Bin"), 2);
					}else{
						SetValueInteger($this->GetIDForIdent("Bin"), 1);
					}
				}else{
					SetValueInteger($this->GetIDForIdent("Bin"), 0);
				}
			}
			
			$this->CheckMissionStatus();
			$this->Disconnect();
		}finally{
			SetValueInteger($this->GetIDForIdent("Control"), 0);
		}
	}
	
	public function Dock() {
		try{
			$this->Connect(['cleanMissionStatus']);
			$this->roomba->Dock();
			$this->roomba->loop();
			$this->CheckMissionStatus();
			$this->Disconnect();
		}finally{
			SetValueInteger($this->GetIDForIdent("Control"), 0);
		}
	}
	
	public function Start() {
		try{
			$this->Connect(['cleanMissionStatus']);
			$this->roomba->Start();
			$this->roomba->loop();
			$this->CheckMissionStatus();
			$this->Disconnect();
		}finally{
			SetValueInteger($this->GetIDForIdent("Control"), 0);
		}
	}
	
	public function Stop() {
		try{
			$this->Connect(['cleanMissionStatus']);
			$this->roomba->Stop();
			$this->roomba->loop();
			$this->CheckMissionStatus();
			$this->Disconnect();
		}finally{
			SetValueInteger($this->GetIDForIdent("Control"), 0);
		}
	}
	
	public function Pause() {
		try{
			$this->Connect(['cleanMissionStatus']);
			$this->roomba->Pause();
			$this->roomba->loop();
			$this->CheckMissionStatus();
			$this->Disconnect();
		}finally{
			SetValueInteger($this->GetIDForIdent("Control"), 0);
		}
	}
	
	public function Resume() {
		try{
			$this->Connect(['cleanMissionStatus']);
			$this->roomba->Resume();
			$this->roomba->loop();
			$this->CheckMissionStatus();
			$this->Disconnect();
		}finally{
			SetValueInteger($this->GetIDForIdent("Control"), 0);
		}
	}
}
