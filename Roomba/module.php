<?
require_once('roomba.php');

// Klassendefinition
class Roomba extends IPSModule {

	// Der Konstruktor des Moduls
	// Überschreibt den Standard Kontruktor von IPS
	public function __construct($InstanceID) {
		// Diese Zeile nicht löschen
		parent::__construct($InstanceID);

		// Selbsterstellter Code
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
	
		//Timer
		$this->RegisterTimer("Update", 600000, 'ROOMBA_Update($_IPS[\'TARGET\']);');
	
		//Variablenprofile
		//Bin
		if(!IPS_VariableProfileExists("ROOMBA.Bin")) {
			IPS_CreateVariableProfile("ROOMBA.Bin", 1);
			IPS_SetVariableProfileValues("ROOMBA.Bin", 0, 2, 1);
			IPS_SetVariableProfileIcon("ROOMBA.MissionState", "Recycling");
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
			IPS_SetVariableProfileAssociation("ROOMBA.Control", 1, $this->Translate("dock"), "", NULL);
			IPS_SetVariableProfileAssociation("ROOMBA.Control", 2, $this->Translate("start"), "", NULL);
			IPS_SetVariableProfileAssociation("ROOMBA.Control", 3, $this->Translate("stop"), "", NULL);
			IPS_SetVariableProfileAssociation("ROOMBA.Control", 4, $this->Translate("pause"), "", NULL);
			IPS_SetVariableProfileAssociation("ROOMBA.Control", 5, $this->Translate("resume"), "", NULL);
		}
	
		$batPct = $this->RegisterVariableInteger("BatPct", $this->Translate("Battery"), "~Battery.100");
		$bin = $this->RegisterVariableInteger("Bin", $this->Translate("Bin"), "ROOMBA.Bin");
		$cleanMissionStatus = $this->RegisterVariableInteger("CleanMissionStatus", $this->Translate("Mission"), "ROOMBA.MissionState");
		$state = $this->RegisterVariableInteger("State", $this->Translate("State"), "ROOMBA.State");

		$this->RegisterVariableInteger("Control", $this->Translate("Control"), "ROOMBA.Control");
		$this->EnableAction("Control");
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

	$roomba = NULL;

	private function Connect($needValues) {
		$this->roomba = new RoombaConnector($this->ReadPropertyString('Address'), $this->ReadPropertyString('Username'), $this->ReadPropertyString('Password'), $needValues);
	}

	private function Disconnect(){
		$this->roomba->disconnect();
	}

	private function CheckMissionStatus(){
		if($roomba->ContainsValue('cleanMissionStatus')){
			switch($roomba->GetValue('cleanMissionStatus')->phase){
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
			SetValueInteger($this->GetIDForIdent("State"), $roomba->GetValue('cleanMissionStatus')->notReady);
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

			$this->roomba->loop();

			if($roomba->ContainsValue('batPct')){
				SetValueInteger($this->GetIDForIdent("BatPct"), $roomba->GetValue('batPct'));
			}
			
			if($roomba->ContainsValue('bin')){
				if($roomba->GetValue('bin')->present){
					if($roomba->GetValue('bin')->full){
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