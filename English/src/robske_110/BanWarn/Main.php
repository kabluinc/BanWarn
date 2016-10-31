<?php

namespace robske_110\BanWarn;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\server\ServerCommandEvent;
use pocketmine\Player;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as TF;

class Main extends PluginBase implements Listener{
	public $warnsys;
	public $clientBan;
	public $config;
	public $tempWPusers = [];

	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		@mkdir($this->getDataFolder());
		$this->warnsys = new Config($this->getDataFolder() . "warnsys.yml", Config::YAML, array());
		$this->warnsys->save();
		$this->clientBan = new Config($this->getDataFolder() . "clientBan.yml", Config::YAML, array());
		$this->clientBan->save();
		$this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML, array());
		if($this->config->get("ConfigVersion") != 3){
			$this->config->set('max-points-until-ban', 10);
			$this->config->set('IP-Ban', true);
			$this->config->set('Client-Ban', true);
			$this->config->set('Notify-Mode', 1);
			$this->config->set('ConfigVersion', 3);
		}
		$this->config->save();
	}
    
	private function parseWPpromptMsg($msg, $playerName, $sender){
		$doEnd = true;
		if($msg == "abort"){
			$this->sendMsgToSender($sender, TF::RED."Aborted the warnpardon prompt"); //TODO::Translate
		}elseif($msg == "last"){
			$remResult = $this->removeLastWarn($playerName);
			var_dump($remResult);
			if($remResult["warnsys"] && $remResult["clientBan"] && $remResult["ipBan"]){
				$this->sendMsgToSender($sender, TF::GREEN."The last warn from '".TF::DARK_GRAY.$playerName.TF::GREEN."' has been removed! He/she has been unbanned. A server restart may be necassary."); //TODO::Translate TODO::FixServerRestartNeed
			}elseif($remResult["warnsys"] && $remResult["clientBan"]){
				$this->sendMsgToSender($sender, TF::GREEN."The last warn from '".TF::DARK_GRAY.$playerName.TF::GREEN."' has been removed! He/she has been unbanned."); //TODO::Translate
			}elseif($remResult["warnsys"]){
				$this->sendMsgToSender($sender, TF::GREEN."The last warn from '".TF::DARK_GRAY.$playerName.TF::GREEN."' has been removed!"); //TODO::Translate
			}else{
				$this->sendMsgToSender($sender, TF::RED."The player '".TF::DARK_GRAY.$playerName.TF::RED."' has no warnings!"); //TODO::Translate
			}
		}elseif($msg == "all"){
			$wipeResult = $this->wipePlayer($playerName);
			var_dump($wipeResult);
			if($wipeResult["warnsys"] && $wipeResult["clientBan"] && $wipeResult["ipBan"]){
				$this->sendMsgToSender($sender, TF::GREEN."All warns from '".TF::DARK_GRAY.$playerName.TF::GREEN."' have been removed! A server restart may be necassary."); //TODO::Translate TODO::FixServerRestartNeed
			}elseif($wipeResult["warnsys"]){
				$this->sendMsgToSender($sender, TF::GREEN."All warns from '".TF::DARK_GRAY.$playerName.TF::GREEN."' have been removed!"); //TODO::Translate
			}else{
				$this->sendMsgToSender($sender, TF::RED."The player '".TF::DARK_GRAY.$playerName.TF::RED."' has no warnings!"); //TODO::Translate
			}
		}else{
			$this->sendMsgToSender($sender, TF::GREEN."You are currently in the warnpardon prompt (Player: '".TF::DARK_GRAY.$playerName.TF::GREEN."')"); //TODO::Translate
			$this->sendMsgToSender($sender, TF::GREEN."If you want to abort this simply type 'abort'"); //TODO::Translate
			$this->sendMsgToSender($sender, TF::GREEN."Type 'all' to remove all warns."); //TODO::Translate
			$this->sendMsgToSender($sender, TF::GREEN."Type 'last' to remove the last warn."); //TODO::Translate
			$doEnd = false;
		}
		return $doEnd;
	}
	
	public function onChat(PlayerChatEvent $event){
		if(isset($this->tempWPusers[$event->getPlayer()->getName()])){
			$msg = strtolower($event->getMessage());
			$sender = $event->getPlayer();
			$playerName = $this->tempWPusers[$event->getPlayer()->getName()];
			$event->setCancelled(true);
			if($this->parseWPpromptMsg($msg, $playerName, $sender)){
				unset($this->tempWPusers[$event->getPlayer()->getName()]);
			}
		}
	}
	public function onConsoleChat(ServerCommandEvent $event){
		if(isset($this->tempWPusers["C.O.N.S.O.L.E_moreThan16Characters"])){
			$msg = strtolower($event->getCommand());
			$sender = $event->getSender();
			$event->setCancelled(true);
			$playerName = $this->tempWPusers["C.O.N.S.O.L.E_moreThan16Characters"];
			if($this->parseWPpromptMsg($msg, $playerName, $sender)){
				unset($this->tempWPusers["C.O.N.S.O.L.E_moreThan16Characters"]);
			}
		}
	}

	public function onCommand(CommandSender $sender, Command $command, $label, array $args){
		switch($command->getName()){
			case "warn":
			if(isset($args[2])){
				if(ctype_digit($args[2])){
					if($this->getServer()->getPlayer($args[0]) instanceof Player){
						$this->addOnlineWarn($args, $sender);
					}else{
						$this->addOfflineWarn($args, $sender);
					}
				}
				else{return false;}   
			}elseif(isset($args[1])){
				$args[2] = 1;
				if($this->getServer()->getPlayer($args[0]) instanceof Player){
					$this->addOnlineWarn($args, $sender);
				}else{
					$this->addOfflineWarn($args, $sender);
				}
			}else{return false;}   
			return true;
			break; 
			case "warninfo":
			if(isset($args[0])){
				if($this->getServer()->getPlayer($args[0]) instanceof Player){
					$playerName = strtolower($this->getServer()->getPlayer($args[0])->getName());
					$playerID = $this->getServer()->getPlayer($args[0])->getClientID();
				}else{
					$playerName = strtolower($args[0]);
					$playerID = $this->getWarnPlayerByName($playerName);
				}
				if($this->warnsys->exists($playerID) && count($tempStuffArray = $this->warnsys->get($playerID)) > 1){
					$this->sendMsgToSender($sender, TF::GREEN."Warnings for the player '".TF::DARK_GRAY.$playerName.TF::GREEN."', who has ".$this->countWPoints($playerID)." Points:"); //TODO::Translate
					$index = 0;
					foreach($tempStuffArray as $playerData){
						if($index != 0){
							$this->sendMsgToSender($sender, TF::GREEN."Warning ".TF::WHITE.$index.":"); //TODO::Translate
							$this->sendMsgToSender($sender, TF::GREEN."Reason: ".TF::DARK_GRAY.$playerData[0]); //TODO::Translate
							$this->sendMsgToSender($sender, TF::GREEN."Points: ".TF::DARK_GRAY.$playerData[1]); //TODO::Translate
						}		
						$index++;
					}
				}else{
					$this->sendMsgToSender($sender, TF::RED."There are no warnings for the player '".TF::DARK_GRAY.$playerName.TF::RED."'!"); //TODO::Translate
				}
			}else{return false;}  
			return true;
			break;
			case "warnpardon":
			if(isset($args[0])){
				if($sender instanceof Player){
					$this->tempWPusers[$sender->getName()] = strtolower($args[0]);
				}else{
					$this->tempWPusers["C.O.N.S.O.L.E_moreThan16Characters"] = strtolower($args[0]); //So it won't conflict with player names
				}
				$this->sendMsgToSender($sender, TF::GREEN."You are going to remove one warn or wipe all warns from the Player '".TF::DARK_GRAY.strtolower($args[0]).TF::GREEN."'!");
				$this->sendMsgToSender($sender, TF::GREEN."If you want to abort this simply type 'abort'");
				$this->sendMsgToSender($sender, TF::GREEN."Type 'all' to remove all warns.");
				$this->sendMsgToSender($sender, TF::GREEN."Type 'last' to remove the last warn.");
			}else{return false;}
			return true;
		}
	}
	
	private function addOnlineWarn($args, $sender){
		$playerName = strtolower($this->getServer()->getPlayer($args[0])->getName());
		$playerID = $this->getServer()->getPlayer($args[0])->getClientId();
		$array = $this->warnsys->get($playerID, []); //Returns an empty array if the player has no previous warnings #FunFact this is the only line written by someone else @PEMapModder :)
		$index = count($array);
		if($index == 0){
			$array[0] = ["RealPlayerName" => $playerName, "RealClientID" => $playerID];
			$index++;
		}
		$array[$index] = [$args[1], $args[2]];
		$this->warnsys->set($playerID, $array);
		$this->warnsys->save();
		$tempMsgS = TF::GREEN . "The player '".TF::DARK_GRAY.$playerName.TF::GREEN."' has been warned with the reason '".TF::DARK_GRAY.$args[1].TF::GREEN."' and ".TF::DARK_GRAY.$args[2].TF::GREEN." point(s)! He/she now has a total of ".TF::DARK_GRAY.$this->countWPoints($playerID).TF::GREEN." point(s)."; //TODO::Translate
		$tempMsgToP = TF::RED . "YOU HAVE BEEN WARNED BY '".$sender->getName()."' WITH THE REASON '".TF::DARK_GRAY.$args[1].TF::RED."' and ".TF::DARK_GRAY.$args[2].TF::RED." POINT(S)! YOU NOW HAVE A TOTAL OF ".TF::DARK_GRAY.$this->countWPoints($playerID).TF::RED." POINT(S)! WITH ".TF::DARK_GRAY.$this->config->get("max-points-until-ban").TF::RED." POINTS YOU WILL BE BANNED!"; //TODO::Translate
		$this->getServer()->getPlayer($args[0])->sendMessage($tempMsgToP);
		if($this->config->get("Notify-Mode") == 1){
			$this->sendMsgToSender($sender, $tempMsgS);
			if($this->getTypeAsNameOfSender($sender) != "CONSOLE"){
				$this->getServer()->getLogger()->info($tempMsgS);
			}
		}elseif($this->config->get("Notify-Mode") == 2){
			$this->getServer()->broadcastMessage($tempMsgS);
		}
		if($this->countWPoints($playerID) >= $this->config->get("max-points-until-ban")){
			$reason = "";
			$index = 0;
			foreach($this->warnsys->get($playerID) as $playerData){
				if($index != 0){
					$reason = $reason.TF::GREEN."W ".TF::WHITE.$index.": ".TF::GREEN."Reason: ".TF::GOLD.$playerData[0]."\n"; //TODO::Translate
				}
				$index++;
			}
			$reason = "You are banned: \n".$reason; //TODO::Translate
			//IP_Ban
			$ip = $this->getServer()->getPlayer($args[0])->getAddress();
			$this->banIP($ip, $reason, $playerName, $this->getTypeAsNameOfSender($sender));
			//Client-Ban
			$this->banClient($playerName, $playerID);
		}
	}
	private function addOfflineWarn($args, $sender){
		$playerName = strtolower($args[0]);
		$playerID = $this->getWarnPlayerByName($playerName);
		if($playerID != NULL){
			$array = $this->warnsys->get($playerID, []); //Returns an empty array if the player has no previous warnings
			$index = count($array);
			if($index == 0){
				$array[0] = ["RealPlayerName" => $playerName, "RealClientID" => $playerID];
				$index++;
			}
			$array[$index] = [$args[1], $args[2]];
			$this->warnsys->set($playerID, $array);
			$this->warnsys->save();
			$tempMsgS = TF::GREEN . "The player '".TF::DARK_GRAY.$playerName.TF::GREEN."' has been warned with the reason '".TF::DARK_GRAY.$args[1].TF::GREEN."' and ".TF::DARK_GRAY.$args[2].TF::GREEN." Point(s)! He now has a total of ".TF::DARK_GRAY.$this->countWPoints($playerID).TF::GREEN." Points."; //TODO::Translate
			if($this->config->get("Notify-Mode") == 1){
				$this->sendMsgToSender($sender, $tempMsgS);
				if($this->getTypeAsNameOfSender($sender) != "CONSOLE"){
					$this->getServer()->getLogger()->info($tempMsgS);
				}
			}elseif($this->config->get("Notify-Mode") == 2){
				$this->getServer()->broadcastMessage($tempMsgS);
			}
			if($this->countWPoints($playerID) >= $this->config->get("max-points-until-ban")){
				$this->sendMsgToSender($sender, TF::RED."The player '".TF::DARK_GRAY.$playerName.TF::RED."' will be banned on his next login!"); //TODO::Translate
			}
		}else{
			$this->sendMsgToSender($sender, TF::RED."Unfortunaly '".TF::DARK_GRAY.$playerName.TF::RED."' could not be warned, as he/she is not online and has no prevoius warnings!"); //TODO::Translate //TODO::FixThis (By using player.dat maybe (WaitingForPM)? HEY, POCKETMINE:WHY ISN'T THERE AN EASY SOLOUTION FOR THIS!)
		}
	}
	
	private function wipePlayer($playerName){
		$remSuceededLvls = ["warnsys" => false, "clientBan" => false, "ipBan" => false];
		$playerID = $this->getWarnPlayerByName($playerName);
		if($this->warnsys->exists($playerID)){
			$remSuceededLvls["warnsys"] = true;
			$this->warnsys->remove($playerID);
			$this->warnsys->save();
		}
		if($this->clientBan->exists($playerName)){
			$remSuceededLvls["clientBan"] = true;
			$this->clientBan->remove($playerName);
			$this->clientBan->save();
		}
		foreach($this->getServer()->getIPBans()->getEntries() as $ipBanObject){
			if($ipBanObject->getReason() == "BanWarnPluginBan BannedPlayer:".$playerName){
				$ip = $ipBanObject->getName();
				$this->getServer()->getIPBans()->remove($ip);
				$remSuceededLvls["ipBan"] = true;
			}
		}
		return $remSuceededLvls;
	}
	private function removeLastWarn($playerName){
		$remSuceededLvls = ["warnsys" => false, "clientBan" => false, "ipBan" => false];
		$playerID = $this->getWarnPlayerByName($playerName);
		if($this->warnsys->exists($playerID)){
			$remSuceededLvls["warnsys"] = true;
			$warnData = $this->warnsys->get($playerID);
			$index = count($warnData);
			$index--;
			unset($warnData[$index]);
			$this->warnsys->set($playerID, $warnData);
			$this->warnsys->save();
		}
		if($this->countWPoints($playerID) < $this->config->get("max-points-until-ban")){
			if($this->clientBan->exists($playerName)){
				$remSuceededLvls["clientBan"] = true;
				$this->clientBan->remove($playerName);
				$this->clientBan->save();
			}
			foreach($this->getServer()->getIPBans()->getEntries() as $ipBanObject){
				if($ipBanObject->getReason() == "BanWarnPluginBan BannedPlayer:".$playerName){
					$ip = $ipBanObject->getName();
					$this->getServer()->getIPBans()->remove($ip);
					$remSuceededLvls["ipBan"] = true;
				}
			}
		}
		return $remSuceededLvls;
	}
	
	private function banClient($playerName, $playerID){
		if($this->config->get("Client-Ban")){
			$this->clientBan->set($playerName, $playerID);
			$this->clientBan->save();
		}
	}
	private function banIP($ip, $reason, $playerName = "unknown", $issuer = "unknown"){
		foreach($this->getServer()->getOnlinePlayers() as $player){
			if($player->getAddress() === $ip){
				$player->kick($reason, false);
			}
		}
		if($this->config->get("IP-Ban")){
			$this->getServer()->getNetwork()->blockAddress($ip, -1);
			$this->getServer()->getIPBans()->addBan($ip, "BanWarnPluginBan BannedPlayer:".$playerName, null, $issuer);
		}
	}
	private function sendMsgToSender($sender, $message){
		if($sender instanceof Player){
			$sender->getPlayer()->sendMessage($message);
		}else{
			$this->getServer()->getLogger()->info("[WarnBan] ".$message);
		}
	}
	public function countWPoints($playerID){
		$tempStuffArray = $this->warnsys->get($playerID);
		if($tempStuffArray != NULL){
			$count = 0;
			$index = 0;
			foreach($tempStuffArray as $playerData){
				if($index != 0){
					$count = $count + $playerData[1];
				}
				$index++;
			}
			return $count;
		}
		return -1;
	}
	private function getTypeAsNameOfSender($sender){
		if($sender instanceof Player){
			$NAME = $sender->getPlayer()->getName();
		}else{
			$NAME = "CONSOLE";
		}
		return $NAME;
	} 
	private function getWarnPlayerByName($playerName){
		$playerID = NULL;
		$tempStuffArray = $this->warnsys->getAll();
		if($tempStuffArray != NULL){
			foreach($tempStuffArray as $warnObject){
				if(isset($warnObject[0])){
					if(isset($warnObject[0]['RealPlayerName'])){
						$realPlayerName = $warnObject[0]['RealPlayerName'];
						if($realPlayerName == $playerName){
							$playerID = $warnObject[0]['RealClientID'];
							break;
						}
					}
				}
			}
		}
		return $playerID;
	}
}
//Theory is when you know something, but it doesn't work. Practice is when something works, but you don't know why. Programmers combine theory and practice: Nothing works and they don't know why!
