<?
class MiFlora extends IPSModule
{
    var $moduleName = "MiFlora";
	
	const LOGLEVEL_INFO = 1;
	const LOGLEVEL_WARNING = 2;
	const LOGLEVEL_DEBUG = 3;
		
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        
        $this->RegisterPropertyString("mifloraHubs", "");
		$this->RegisterPropertyInteger("UpdateIntervall", 10);
        $this->RegisterPropertyBoolean("MinMaxEnabled", false);
        $this->RegisterPropertyBoolean("DataLoggingEnabled", false);
        $this->RegisterPropertyBoolean("BatteryCheckEnabled", false);
        $this->RegisterPropertyBoolean("CreateReadableTextEnabled", false);
        $this->RegisterPropertyString("FormSensorList", "");
		
        $this->RegisterPropertyInteger("Debug", 0);
		$this->CreateVariable("SensorList", 3,"{}", "miFloraSensorList", $this->InstanceID );
		
		// --------------------------------------------------------
        // Timer installieren
        // --------------------------------------------------------
        $this->RegisterTimer("UpdateTimer", 0, 'MiFlora_Update($_IPS[\'TARGET\']);');

		
		// --------------------------------------------------------
        // Variablen Profile einrichten
        // --------------------------------------------------------
        if (!IPS_VariableProfileExists("MiFlora_LUX"))
        {
            IPS_CreateVariableProfile("MiFlora_LUX", 2);
            IPS_SetVariableProfileText("MiFlora_LUX", "", " Lx");
			IPS_SetVariableProfileValues("MiFlora_LUX",0, 10000, 100 );
        }
		
        if (!IPS_VariableProfileExists("MiFlora_EC"))
        {
            IPS_CreateVariableProfile("MiFlora_EC", 2);
            IPS_SetVariableProfileText("MiFlora_EC", "", " µs/cm");
			IPS_SetVariableProfileValues("MiFlora_EC",0, 1000, 100 );
        }
		
        if (!IPS_VariableProfileExists("MiFlora_Humidity"))
        {
            IPS_CreateVariableProfile("MiFlora_Humidity", 2);
			IPS_SetVariableProfileValues("MiFlora_Humidity",0, 100, 10 );
			IPS_SetVariableProfileAssociation("MiFlora_Humidity", 0, "%d %%", "", -1);
        }

        if (!IPS_VariableProfileExists("MiFlora_Temperature"))
        {
            IPS_CreateVariableProfile("MiFlora_Temperature", 2);
			IPS_SetVariableProfileValues("MiFlora_Temperature",-15, 45, 5 );
            IPS_SetVariableProfileText("MiFlora_Temperature", "", " °C");
        }

        if (!IPS_VariableProfileExists("MiFlora_Battery"))
        {
            IPS_CreateVariableProfile("MiFlora_Battery", 2);
			IPS_SetVariableProfileValues("MiFlora_Battery",0, 100, 10 );
			IPS_SetVariableProfileAssociation("MiFlora_Battery", 0, "%d %%", "", 0xff0000);
			IPS_SetVariableProfileAssociation("MiFlora_Battery", 25, "%d %%", "", 0xffae00);
			IPS_SetVariableProfileAssociation("MiFlora_Battery", 50, "%d %%", "", 0xaeff00);
			IPS_SetVariableProfileAssociation("MiFlora_Battery", 75, "%d %%", "", 0x00ff00);
        }

        if (!IPS_VariableProfileExists("MiFlora_Temperature_Hint"))
        {
            IPS_CreateVariableProfile("MiFlora_Temperature_Hint", 1);
			IPS_SetVariableProfileAssociation("MiFlora_Temperature_Hint", 0, "OK", "", 0x00ff00);
			IPS_SetVariableProfileAssociation("MiFlora_Temperature_Hint", 1, "Zu kalt", "", 0xff0000);
			IPS_SetVariableProfileAssociation("MiFlora_Temperature_Hint", 2, "Zu warm", "", 0xff0000);
		}
		
        if (!IPS_VariableProfileExists("MiFlora_Fertilize_Hint"))
        {
            IPS_CreateVariableProfile("MiFlora_Fertilize_Hint", 1);
			IPS_SetVariableProfileAssociation("MiFlora_Fertilize_Hint", 0, "OK", "", 0x00ff00);
			IPS_SetVariableProfileAssociation("MiFlora_Fertilize_Hint", 1, "Düngen", "", 0xff0000);
			IPS_SetVariableProfileAssociation("MiFlora_Fertilize_Hint", 2, "Überdüngt", "", 0xff0000);
        }
		
        if (!IPS_VariableProfileExists("MiFlora_Humidity_Hint"))
        {
            IPS_CreateVariableProfile("MiFlora_Humidity_Hint", 1);
			IPS_SetVariableProfileAssociation("MiFlora_Humidity_Hint", 0, "OK", "", 0x00ff00);
			IPS_SetVariableProfileAssociation("MiFlora_Humidity_Hint", 1, "Zu trocken", "", 0xff0000);
			IPS_SetVariableProfileAssociation("MiFlora_Humidity_Hint", 2, "Zu nass", "", 0xff0000);
        }

        if (!IPS_VariableProfileExists("MiFlora_LUX_Hint"))
        {
            IPS_CreateVariableProfile("MiFlora_LUX_Hint", 1);
			IPS_SetVariableProfileAssociation("MiFlora_LUX_Hint", 0, "OK", "", 0x00ff00);
			IPS_SetVariableProfileAssociation("MiFlora_LUX_Hint", 1, "Zu dunkel", "", 0xff0000);
			IPS_SetVariableProfileAssociation("MiFlora_LUX_Hint", 2, "Zu hell", "", 0xff0000);
        }
		
		
		// Verzeichnis für Standortbilder
		@mkdir(IPS_GetKernelDir()."webfront".DIRECTORY_SEPARATOR."user".DIRECTORY_SEPARATOR."MiFlora".DIRECTORY_SEPARATOR);

		$this->RegisterScript("SetValue","SetValue","<?\n\nSetValue(\$_IPS[\"VARIABLE\"],\$_IPS[\"VALUE\"]);\n\n?>");
		
		$content = @file_get_contents(__DIR__ . "/processTemperature.php");			
		$this->RegisterScript("ProcessTemperature","ProcessTemperature",$content);

		$content = @file_get_contents(__DIR__ . "/processLUX.php");			
		$this->RegisterScript("ProcessLUX","ProcessLUX",$content);

	}
    
    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();		

		// --------------------------------------------------------
        // Timer starten
        // --------------------------------------------------------
        $this->SetTimerInterval("UpdateTimer", $this->ReadPropertyInteger("UpdateIntervall")*1000);		
		
	}
	
	public function Update()
    {
		$sensorArray = NULL;
		$updateScriptID = @IPS_GetScriptIDByName("SetValue",$this->InstanceID);
		$checkTemperatureScriptID = @IPS_GetScriptIDByName("CheckTemperatures",$this->InstanceID);
		
		$sensorListID = IPS_GetVariableIDByName("SensorList",$this->InstanceID);
		$sensorList = json_decode(GetValue($sensorListID),true);
		
        $this->logThis("Updating from miflora hubs",self::LOGLEVEL_INFO);

		$mifloraConfig = $this->ReadPropertyString("mifloraHubs");
		$mifloraHubs = json_decode($mifloraConfig);

		if (!is_array($mifloraHubs))
		{
			$this->logThis("No hubs defined!",self::LOGLEVEL_WARNING);
			return;
		}
		
		
		foreach($mifloraHubs as $mifloraHub)
		{
			$dataPath = "http://".$mifloraHub->hubIP."/plants.log";

			$this->logThis("Getting data from [".$mifloraHub->name."]",self::LOGLEVEL_INFO);
			$this->logThis("DataPath=".$dataPath,self::LOGLEVEL_DEBUG);
			
			$flowerLog = @file_get_contents($dataPath);

			if ($flowerLog === false)
			{
				$this->logThis("No data found on [".$mifloraHub->name."]",self::LOGLEVEL_WARNING);
				continue;
			}
			
			$this->logThis($flowerLog,self::LOGLEVEL_DEBUG);
			
			// 
//			$flowerLog = preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $flowerLog);

			// Jede Zeile ein Sensor
			$flowerArray = explode("\n",$flowerLog);
			$flowerArraySize = sizeof($flowerArray) - 2;

			
			// Das Skript könnte noch laufen. Es muss anständig abgeschlossen sein!
			if ($flowerArraySize < 0 || $flowerArray[$flowerArraySize] != "DONE!")
			{
				$this->logThis("MiFlora log (".$mifloraHub->name.") incomplete!",self::LOGLEVEL_WARNING);
				continue;
			}

			$this->logThis(($flowerArraySize)." sensors found on [".$mifloraHub->name."]",self::LOGLEVEL_INFO);
			
			foreach($flowerArray as $sensor)
			{
				// (18:30:03 20-04-2017) Mac=C4:7C:8D:61:67:9C Name=Flower care Fw=2.9.2 Temp=20.50 Moist=4 Light=126 Cond=0 Bat=100
				preg_match('/\((.*)\) Mac=(.*) Name=(.*) Fw=(.*) Temp=(.*) Moist=(.*) Light=(.*) Cond=(.*) Bat=(.*)/',$sensor,$result);

				// Zeilen ohne Sinn und Verstand einfach ignorieren
				if (sizeof($result) != 10)
					continue;
				
				$this->logThis(print_r($result,true),self::LOGLEVEL_DEBUG);
				
				$uuid = str_replace(":","",$result[2]);
				
				$sensorArray[$uuid]["LastMessage"] = $result[1];				
				$sensorArray[$uuid]["UUID"] = $result[2];
				$sensorArray[$uuid]["Firmware"] = $result[4];
				$sensorArray[$uuid]["Temperature"] = $result[5];
				$sensorArray[$uuid]["SoilMoisture"] = $result[6];
				$sensorArray[$uuid]["Lux"] = $result[7];
				$sensorArray[$uuid]["SoilElectricalConductivity"] = $result[8];
				$sensorArray[$uuid]["BatteryLevel"] = $result[9];
				$sensorArray[$uuid]["Hubs"][] = $mifloraHub->name;
			}
		}
		
		// Jetzt erst die Daten schreiben - Sensoren können von mehreren Hubs erwischt werden
		if (@is_array($sensorArray))
		{
			$archiveID = IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}')[0];
			
			foreach($sensorArray as $uuid => $sensor)
			{

				$catID = $this->CreateCategory($sensor["UUID"]." (In Instanz umbenennen!)", $uuid , $this->InstanceID);
				$this->CreateVariable("Letzte Meldung", 3, $sensor["LastMessage"], $uuid."_lastMessage", $catID );
				$this->CreateVariable("UUID", 3, $sensor["UUID"], $uuid."_uuid", $catID );
				$this->CreateVariable("Firmware", 3, $sensor["Firmware"], $uuid."_firmware", $catID);
				$logVarIDs[] = $this->CreateVariable("Bodenfeuchtigkeit", 2, $sensor["SoilMoisture"], $uuid."_soilMoisture", $catID ,"MiFlora_Humidity");
				$logVarIDs[] = $this->CreateVariable("Bodenleitfähigkeit", 2, $sensor["SoilElectricalConductivity"], $uuid."_soilElectricalConductivity", $catID ,"MiFlora_EC");
				$logVarIDs[] = $this->CreateVariable("Zustand Batterie", 2, $sensor["BatteryLevel"], $uuid."_batteryLevel", $catID, "MiFlora_Battery" );
				$this->CreateVariable("Hubs", 3, join($sensor["Hubs"],", "), $uuid."_hubs", $catID );

				// ----------------------------
				// Temperatur + Skript
				// ----------------------------
				$varID = $this->CreateVariable("Temperatur", 2, $sensor["Temperature"], $uuid."_temperature", $catID ,"MiFlora_Temperature");
				$logVarIDs[] = $varID;

				$eventID = @IPS_GetEventIDByName ("ProcessTemperature", $varID);
				if ($eventID === false)
				{
					$eventID = IPS_CreateEvent(1);
					IPS_SetParent($eventID, $varID);
					IPS_SetName($eventID,"ProcessTemperature");
					IPS_SetEventCyclicTimeFrom($eventID, 23, 59, 50);
					$scriptID = IPS_GetScriptIDByName("ProcessTemperature", $this->InstanceID);
					$scriptContent = "IPS_RunScriptEx(".$scriptID.",Array(\"TARGET\" => \$_IPS[\"TARGET\"]));";					
					IPS_SetEventScript($eventID,$scriptContent);
					IPS_SetEventActive($eventID, true);
					IPS_SetHidden($eventID,true);
				}
				
				// ----------------------------
				// Beleuchtungsstärke + Skript
				// ----------------------------
				
				$varID = $this->CreateVariable("Beleuchtungsstärke", 2, $sensor["Lux"], $uuid."_lux", $catID ,"MiFlora_LUX" );
				$logVarIDs[] = $varID;
				
				$eventID = @IPS_GetEventIDByName ("ProcessLUX", $varID);
				if ($eventID === false)
				{
					$eventID = IPS_CreateEvent(1);
					IPS_SetParent($eventID, $varID);
					IPS_SetName($eventID,"ProcessLUX");
					IPS_SetEventCyclicTimeFrom($eventID, 23, 59, 50);
					
					$scriptID = IPS_GetScriptIDByName("ProcessLUX", $this->InstanceID);
					$scriptContent = "IPS_RunScriptEx(".$scriptID.",Array(\"TARGET\" => \$_IPS[\"TARGET\"]));";					
					IPS_SetEventScript($eventID,$scriptContent);
					IPS_SetEventActive($eventID, true);
					IPS_SetHidden($eventID,true);
				}

				// ----------------------------
				// Sensorliste erstellen
				// ----------------------------

				$catObj = IPS_GetObject($catID);				
				$sensorList[$sensor["UUID"]]["name"] = $catObj["ObjectName"];
				$sensorList[$sensor["UUID"]]["uuid"] = $sensor["UUID"];

				// Messwerte prüfen
				if ($this->ReadPropertyBoolean("MinMaxEnabled"))
				{					
					// -------------------------------------------------------------
					// Einmalig zu erzeugende Variablen (Stellschrauben User)
					// -------------------------------------------------------------
					if (@IPS_GetVariableIDByName("Bodenfeuchtigkeit MAX", $catID) === false)
					{
						$varID = $this->CreateVariable("Bodenfeuchtigkeit MAX", 2, 50, $uuid."_moistMax", $catID ,"MiFlora_Humidity");
						IPS_SetVariableCustomAction($varID, $updateScriptID);
						$logVarIDs[] = $varID;
					}
					if (@IPS_GetVariableIDByName("Bodenfeuchtigkeit MIN", $catID) === false)
					{
						$varID = $this->CreateVariable("Bodenfeuchtigkeit MIN", 2, 20, $uuid."_moistMin", $catID ,"MiFlora_Humidity");
						IPS_SetVariableCustomAction($varID, $updateScriptID);
						$logVarIDs[] = $varID;
					}
					if (@IPS_GetVariableIDByName("Bodenleitfähigkeit MAX", $catID) === false)
					{
						$varID = $this->CreateVariable("Bodenleitfähigkeit MAX", 2, 100, $uuid."_ecMax", $catID ,"MiFlora_EC");
						IPS_SetVariableCustomAction($varID, $updateScriptID);
						$logVarIDs[] = $varID;
					}
					if (@IPS_GetVariableIDByName("Bodenleitfähigkeit MIN", $catID) === false)
					{
						$varID = $this->CreateVariable("Bodenleitfähigkeit MIN", 2, 30, $uuid."_ecMin", $catID ,"MiFlora_EC");
						IPS_SetVariableCustomAction($varID, $updateScriptID);
						$logVarIDs[] = $varID;
					}

					if (@IPS_GetVariableIDByName("Temperatur MAX", $catID) === false)
					{
						$varID = $this->CreateVariable("Temperatur MAX", 2, 45, $uuid."_tempMax", $catID ,"MiFlora_Temperature");
						IPS_SetVariableCustomAction($varID, $updateScriptID);
						$logVarIDs[] = $varID;
					}
					if (@IPS_GetVariableIDByName("Temperatur MIN", $catID) === false)
					{
						$varID = $this->CreateVariable("Temperatur MIN", 2, -15, $uuid."_tempMin", $catID ,"MiFlora_Temperature");
						IPS_SetVariableCustomAction($varID, $updateScriptID);
						$logVarIDs[] = $varID;
					}

					if (@IPS_GetVariableIDByName("Beleuchtungsstärke MAX", $catID) === false)
					{
						$varID = $this->CreateVariable("Beleuchtungsstärke MAX", 2, 2500, $uuid."_LUXMax", $catID ,"MiFlora_LUX");
						IPS_SetVariableCustomAction($varID, $updateScriptID);
						$logVarIDs[] = $varID;
					}
					if (@IPS_GetVariableIDByName("Beleuchtungsstärke MIN", $catID) === false)
					{
						$varID = $this->CreateVariable("Beleuchtungsstärke MIN", 2, 1200, $uuid."_LUXMin", $catID ,"MiFlora_LUX");
						IPS_SetVariableCustomAction($varID, $updateScriptID);
						$logVarIDs[] = $varID;
					}
					
					$hum = false;
					$ec = false;

					// MIN/MAX Values laden - Könnten vom User verändert worden sein
					$minHumID = IPS_GetVariableIDByName("Bodenfeuchtigkeit MIN", $catID);
					$maxHumID = IPS_GetVariableIDByName("Bodenfeuchtigkeit MAX", $catID);
					$minECID = IPS_GetVariableIDByName("Bodenleitfähigkeit MIN", $catID);
					$maxECID = IPS_GetVariableIDByName("Bodenleitfähigkeit MAX", $catID);

					$minHumidityValue = GetValue($minHumID);
					$maxHumidityValue = GetValue($maxHumID);
					$minECValue = GetValue($minECID);
					$maxECValue = GetValue($maxECID);

					$this->logThis("Hum=".$sensor["SoilMoisture"]. " Min=".$minHumidityValue." Max=".$maxHumidityValue,self::LOGLEVEL_DEBUG);
					$this->logThis("Con=".$sensor["SoilElectricalConductivity"]. " Min=".$minECValue." Max=".$maxECValue,self::LOGLEVEL_DEBUG);
//					$this->logThis("Temp=".$sensor["Temperature"]. " Min=".$minTempValue." Max=".$maxTempValue,self::LOGLEVEL_DEBUG);
//					$this->logThis("Light=".$sensor["Lux"]. " Min=".$minLightValue." Max=".$maxLightValue,self::LOGLEVEL_DEBUG);
					
					// Feuchtigkeit
					$hintValue = 0;
					
					if ($sensor["SoilMoisture"] < $minHumidityValue)
					{
						$hintValue = 1;
						$readableText["waterPlease"][] = $catObj["ObjectName"];
					}
					else if ($sensor["SoilMoisture"] > $maxHumidityValue)
					{
						$hintValue = 2;
						$readableText["dryPlease"][] = $catObj["ObjectName"];
					}						
					
					$this->CreateVariable("Bodenfeuchtigkeit Hinweis", 1, $hintValue, $uuid."_MoistureHint", $catID ,"MiFlora_Humidity_Hint");

					// Düngen
					$hintValue = 0;

					if ($sensor["SoilElectricalConductivity"] < $minECValue)
					{
						$hintValue = 1;
						$readableText["fertilizePlease"][] = $catObj["ObjectName"];
					}
					else if ($sensor["SoilElectricalConductivity"] > $maxECValue)
					{
						$hintValue = 2;
						$readableText["tooMuchFertilizer"][] = $catObj["ObjectName"];
					}

					$this->CreateVariable("Bodenleitfähigkeit Hinweis", 1, $hintValue, $uuid."_FertilizeHint", $catID ,"MiFlora_Fertilize_Hint");
					

				}
				
				if (@IPS_GetVariableIDByName("Beleuchtungsstärke Hinweis", $catID) === false)
				{
					$varID = $this->CreateVariable("Beleuchtungsstärke Hinweis", 1, 0, $uuid."_LUXHint", $catID ,"MiFlora_LUX_Hint");
				}

				if (@IPS_GetVariableIDByName("Temperatur Hinweis", $catID) === false)
				{
					$varID = $this->CreateVariable("Temperatur Hinweis", 1, 0, $uuid."_TemperatureHint", $catID ,"MiFlora_Temperature_Hint");
				}
				
				
				if ($this->ReadPropertyBoolean("DataLoggingEnabled"))
				{
					if ($archiveID == 0)
					{
						$this->logThis("ArchiveID = 0!",self::LOGLEVEL_WARNING);
					}
					else
					{
						foreach($logVarIDs as $logVar)
						{
							// Nur 1 malig den Zustand aktivieren
							if (!AC_GetLoggingStatus($archiveID, $logVar))
							{
								AC_SetLoggingStatus($archiveID, $logVar, true);
								IPS_ApplyChanges($archiveID);				
							}
						}
					}
				}
				
			}

			if ($this->ReadPropertyBoolean("CreateReadableTextEnabled"))
			{
				if (isset($readableText["waterPlease"]))
					$readableText_waterPlease = "Folgende Pflanzen benötigen heute noch Wasser: ".join($readableText["waterPlease"],",");
				else
					$readableText_waterPlease = "Keine Pflanze benötigt Wasser.";
				
				if (isset($readableText["dryPlease"]))			
					$readableText_dryPlease = "Folgende Pflanzen sind viel zu nass: ".join($readableText["dryPlease"],",");
				else
					$readableText_dryPlease = "Keine Pflanze ist zu nass.";
					
				if (isset($readableText["fertilizePlease"]))			
					$readableText_fertilizePlease = "Folgende Pflanzen benötigen heute noch Dünger: ".join($readableText["fertilizePlease"],",");
				else
					$readableText_fertilizePlease = "Keine Pflanze benötigt dünger.";

/*				
				if (isset($readableText["tooMuchFertilizer"]))			
					$readableText_tooMuchFertilizer = "Folgende Pflanzen sind Überdüngt worden: ".join($readableText["tooMuchFertilizer"],",");
				else
					$readableText_tooMuchFertilizer = "Keine Pflanze ist Überdüngt worden.";
						
				if (isset($readableText["tooBright"]))			
					$readableText_tooBright = "Folgende Pflanzen stehen zu hell: ".join($readableText["tooBright"],",");
				else
					$readableText_tooBright = "Keine Pflanze steht zu hell.";
*/
						
				$this->CreateVariable("Pflegehinweis (Giessen)", 3, $readableText_waterPlease, "miFlora_readableTextWaterPlease", $this->InstanceID);
				$this->CreateVariable("Pflegehinweis (Trocknen)", 3, $readableText_dryPlease, "miFlora_readableTextDryPlease", $this->InstanceID);
				$this->CreateVariable("Pflegehinweis (Düngen)", 3, $readableText_fertilizePlease, "miFlora_readableTextFertilizePlease", $this->InstanceID);
/*
				$this->CreateVariable("Pflegehinweis (Überdüngt)", 3, $readableText_fertilizePlease, "miFlora_readableTextFertilizePlease", $this->InstanceID);
				$this->CreateVariable("Pflegehinweis (Zu dunkel)", 3, $readableText_fertilizePlease, "miFlora_readableTextFertilizePlease", $this->InstanceID);
				$this->CreateVariable("Pflegehinweis (Zu hell)", 3, $readableText_fertilizePlease, "miFlora_readableTextFertilizePlease", $this->InstanceID);
				$this->CreateVariable("Pflegehinweis (Zu kalt)", 3, $readableText_fertilizePlease, "miFlora_readableTextFertilizePlease", $this->InstanceID);
				$this->CreateVariable("Pflegehinweis (Zu warm)", 3, $readableText_fertilizePlease, "miFlora_readableTextFertilizePlease", $this->InstanceID);
*/
				}
			
			SetValue($sensorListID,json_encode($sensorList));
		}
		
	}

    private function CreateCategory( $Name, $Ident = '', $ParentID = 0 )
    {
        global $RootCategoryID;

		$this->logThis("CreateCategory: ($Name,$Ident,$ParentID)",self::LOGLEVEL_DEBUG);

        if ( '' != $Ident )
        {
            $CatID = @IPS_GetObjectIDByIdent( $Ident, $ParentID );
            if ( false !== $CatID )
            {
               $Obj = IPS_GetObject( $CatID );
               if ( 0 == $Obj['ObjectType'] ) // is category?
                  return $CatID;
            }
        }

        $CatID = IPS_CreateCategory();
        IPS_SetName( $CatID, $Name );
        IPS_SetIdent( $CatID, $Ident );

        if ( 0 == $ParentID )
            if ( IPS_ObjectExists( $RootCategoryID ) )
                $ParentID = $RootCategoryID;
        IPS_SetParent( $CatID, $ParentID );

        return $CatID;
    }

    private function SetVariable( $VarID, $Type, $Value )
    {
        switch( $Type )
        {
           case 0: // boolean
              SetValueBoolean( $VarID, $Value );
              break;
           case 1: // integer
              SetValueInteger( $VarID, $Value );
              break;
           case 2: // float
              SetValueFloat( $VarID, $Value );
              break;
           case 3: // string
              SetValueString( $VarID, $Value );
              break;
        }
    }

    private function CreateVariable( $Name, $Type, $Value, $Ident = '', $ParentID = 0 ,$Profil = "")
    {
		$this->logThis("CreateVariable: ($Name,$Type,$Value,$Ident,$ParentID,$Profil)",self::LOGLEVEL_DEBUG);

        if ( '' != $Ident )
        {
            $VarID = @IPS_GetObjectIDByIdent( $Ident, $ParentID );
            if ( false !== $VarID )
            {
               $this->SetVariable( $VarID, $Type, $Value );
               if ($Profil != "")
                    IPS_SetVariableCustomProfile($VarID,$Profil);
               return $VarID;
            }
        }
        $VarID = @IPS_GetObjectIDByName( $Name, $ParentID );
        if ( false !== $VarID ) // exists?
        {
           $Obj = IPS_GetObject( $VarID );
           if ( 2 == $Obj['ObjectType'] ) // is variable?
            {
               $Var = IPS_GetVariable( $VarID );
               if ( $Type == $Var['VariableValue']['ValueType'] )
                {
                   $this->SetVariable( $VarID, $Type, $Value );
                   if ($Profil != "")
                        IPS_SetVariableCustomProfile($VarID,$Profil);

                   return $VarID;
                }
            }
        }

        $VarID = IPS_CreateVariable( $Type );

        IPS_SetParent( $VarID, $ParentID );
        IPS_SetName( $VarID, $Name );

        if ( '' != $Ident )
           IPS_SetIdent( $VarID, $Ident );
           
        $this->SetVariable( $VarID, $Type, $Value );

        if ($Profil != "")
            IPS_SetVariableCustomProfile($VarID,$Profil);
		
		return $VarID;

    }

	private function logThis($message,$logLevel,$logToDisc = false)
	{
		
		if ($this->ReadPropertyInteger("Debug") < $logLevel)
			return;
		
		switch($logLevel)
		{
			case self::LOGLEVEL_DEBUG:
				IPS_LogMessage($this->moduleName." [DBG]",$message);
				break;
			case self::LOGLEVEL_INFO:
				IPS_LogMessage($this->moduleName." [INF]",$message);
				break;
			case self::LOGLEVEL_WARNING:
				IPS_LogMessage($this->moduleName." [WRN]",$message);
				break;
		}

		if ($logToDisc)
		{
			$log = @file_get_contents(__DIR__ . "/log.txt");			
			file_put_contents(__DIR__ . "/log.txt",$log.$message."\n"); 		
		}

	}

	private function RenameCategory($Ident,$Name)
	{
		$CatID = @IPS_GetObjectIDByIdent( $Ident, $this->InstanceID );

		$this->logThis("RenameCategory (Ident:".$Ident.",Name:".$Name.",CatID:".$CatID.")",self::LOGLEVEL_INFO);
		
		if ($CatID !== false)
			IPS_SetName( $CatID, $Name );

		return $CatID;
	}

	private function GetFormSensorDataForUUID($uuid)
	{
		$formSensorList = $this->ReadPropertyString("FormSensorList");
		$formSensorList = json_decode($formSensorList,true);
		
		if (!is_array($formSensorList))
			return false;
		
		foreach($formSensorList as $entry)
		{
			if ($entry["uuid"] == $uuid)
				return $entry;
		}
		
		return false;
	}
	
	
	
	public function GetConfigurationForm()
	{		
		$this->logThis("GetConfigurationForm",self::LOGLEVEL_INFO);
		
		
		$data = json_decode(file_get_contents(__DIR__ . "/form.json")); 		

		
		// Aktuell gefundene Sensoren. 
		if ($sensorListID = @IPS_GetVariableIDByName("SensorList",$this->InstanceID))
			$sensorList = json_decode(GetValue($sensorListID),true);

		
//		$this->logThis("Aktuell gefundene Sensoren=".sizeof($sensorList),self::LOGLEVEL_INFO);
		
		foreach($sensorList as $sensor)
		{			
//			$this->logThis(print_r($sensor,true),self::LOGLEVEL_INFO);
			$uuid = str_replace(":","",$sensor["uuid"]);


			$formSensor = $this->GetFormSensorDataForUUID($sensor["uuid"]);
			
			if ($formSensor != false)
			{
				// Kategorie umbennen
				$catID = $this->RenameCategory($uuid,$formSensor["name"]);
				// Infobild 				
				if (isset($formSensor["mediaID"]) && $formSensor["mediaID"] > 0)
				{
					$imgContent = base64_decode(IPS_GetMediaContent($formSensor["mediaID"]));
					$imgPath = IPS_GetKernelDir()."webfront".DIRECTORY_SEPARATOR."user".DIRECTORY_SEPARATOR."MiFlora".DIRECTORY_SEPARATOR.$uuid.".jpg";
					file_put_contents($imgPath, $imgContent);

					$this->CreateVariable("Standort", 3, "<center><img style='width:100%;' src='/user/MiFlora/".$uuid.".jpg'></center>", $uuid."_place", $catID , "~HTMLBox");
				}
			}
			$data->elements[1]->values[] = $sensor;
		}

		$content = json_encode($data);

//		$this->logThis(print_r($content,true),self::LOGLEVEL_INFO);
		
		return $content;
	
	} 
}
?>
