<?
require_once('../libs/mqtt/MQTT.php');
require_once('../libs/mqtt/SocketClient.php');
require_once('../libs/mqtt/CMDStore.php');
require_once('../libs/mqtt/Utility.php');
require_once('../libs/mqtt/Debug.php');
require_once('../libs/mqtt/Exception.php');
require_once('../libs/mqtt/Exception/NetworkError.php');
require_once('../libs/mqtt/MessageHandler.php');
require_once('../libs/mqtt/Message.php');
require_once('../libs/mqtt/Message/Base.php');
require_once('../libs/mqtt/Message/Header/Base.php');
require_once('../libs/mqtt/Message/CONNECT.php');
require_once('../libs/mqtt/Message/Header/CONNECT.php');
require_once('../libs/mqtt/Message/CONNACK.php');
require_once('../libs/mqtt/Message/Header/CONNACK.php');
require_once('../libs/mqtt/Message/DISCONNECT.php');
require_once('../libs/mqtt/Message/Header/DISCONNECT.php');
require_once('../libs/mqtt/Message/PINGREQ.php');
require_once('../libs/mqtt/Message/Header/PINGREQ.php');
require_once('../libs/mqtt/Message/PUBLISH.php');
require_once('../libs/mqtt/Message/Header/PUBLISH.php');
require_once('../libs/mqtt/Message/PUBACK.php');
require_once('../libs/mqtt/Message/Header/PUBACK.php');
require_once('../libs/mqtt/Message/SUBSCRIBE.php');
require_once('../libs/mqtt/Message/Header/SUBSCRIBE.php');
require_once('../libs/mqtt/PacketIdentifier.php');
require_once('../libs/mqtt/PacketIdentifierStoreInterface.php');
require_once('../libs/mqtt/PacketIdentifierStore/PhpStatic.php');

class RoombaHandler extends sskaje\mqtt\MessageHandler {
	public $needValues = [];
	public $gotValues = [];
	public $data = [];

    public function publish(sskaje\mqtt\MQTT $mqtt, sskaje\mqtt\Message\PUBLISH $publish_object){
		$publish = json_decode($publish_object->getMessage());
		foreach($publish->state->reported as $name => $value) $this->SetValue($name, $value);
    }
	
	private function SetValue($name, $value){
		array_push($this->gotValues, $name);
		$this->data[$name] = $value;
		$newNeed = [];
		foreach($this->needValues as $need) if($need != $name) array_push($newNeed, $need);
		$this->needValues = $newNeed;
	}
}

class RoombaConnector{
	private $connector;
	private $context;
	private $msgHandler;

	function __construct($address, $username, $password, $need){
		$this->msgHandler = new RoombaHandler();
		$this->msgHandler->needValues = $need;
		$this->context = stream_context_create([
		  'ssl' => [
		    'verify_peer' => false,
			'verify_peer_name' => false,
		    'allow_self_signed' => true
		  ],
		]);
		$this->connector = new sskaje\mqtt\MQTT('tls://'.$address.":8883", $username);
		$this->connector->setHandler($this->msgHandler);
		$this->connector->setSocketContext($this->context);
		$this->connector->setVersion(sskaje\mqtt\MQTT::VERSION_3_1_1);
		$this->connector->setAuth($username, $password);
		$this->connector->setConnectClean(false);
				
		if(!$this->connector->connect()){
			$this->close();
			echo "Connection failed";
			throw new Exception('Connection failed');
		}
	}
	
	function printFull(){
		echo print_r($this->msgHandler->data);
	}
	
	function GetValue($name){
		return $this->msgHandler->data[$name];
	}
	
	function ContainsValue($name){
		return array_key_exists($name, $this->msgHandler->data);
	}
	
	function loop(){
		$end=time() + 30;
		do{
			$result = $this->connector->handle_message();
		} while($result AND $end > time() AND count($this->msgHandler->needValues) > 0);
	}
	
	function _apiCall ($topic, $command) {
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
	    
		if(!$this->connector->publish_sync($topic, json_encode($cmd))){
			echo "Fehler beim Befehl";
		}
	}
	
	function Start(){
		$this->_apiCall('cmd', 'start');
		sleep(1);
	}
	
	function Pause(){
		$this->_apiCall('cmd', 'pause');
		sleep(1);
	}
	
	function Stop(){
		$this->_apiCall('cmd', 'stop');
		sleep(1);
	}
	
	function Resume(){
		$this->_apiCall('cmd', 'resume');
		sleep(1);
	}
	
	function Dock(){
		$this->_apiCall('cmd', 'dock');
		sleep(1);
	}
	
	function setAlwaysFinishOn(){
		$this->_apiCall('delta', ['binPause' => false]);
	}
	
	function setAlwaysFinishOff(){
		$this->_apiCall('delta', ['binPause' => true]);
	}
	
	function disconnect(){
		$this->connector->disconnect();
	}
}