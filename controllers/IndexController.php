<?php
class OaipmhHarvester_IndexController extends Omeka_Controller_Action
{    
    public function indexAction()
    {
        $harvestedSets = $this->getTable('OaipmhHarvesterSet')->findAllSets();
        $this->view->harvestedSets = $harvestedSets;
    }
    
    public function setsAction()
    {
    	// Get the available OAI-PMH to Omeka maps, which should correspond to 
    	// OAI-PMH metadata formats.
		$maps = $this->_getMaps();
		    	
    	// Get the available metadata formats from the data provider.
    	$baseUrl = $_POST['base_url'];
    	$requestArguments = array('verb' => 'ListMetadataFormats');
    	$oaipmh = new OaipmhHarvester_Oaipmh($baseUrl, $requestArguments);
    	
		// Compare the available OAI-PMH metadataFormats with the available 
		// Omeka maps and extract only those that are common to both. It's 
		// important to consider that some repositories don't provide repository
		// -wide metadata formats. Instead they only provide record level 
		// metadata formats. Oai_dc is mandatory for all records, so if a 
		// repository doesn't provide metadata formats using 
		// ListMetadataFormats, only expose the oai_dc prefix. For a data 
		// provider that doesn't offer repository-wide metadata formats, see: 
		// http://www.informatik.uni-stuttgart.de/cgi-bin/OAI/OAI.pl
 		$availableMaps = array();
   		if (isset($oaipmh->getOaipmh()->ListMetadataFormats)) {
    		$metadataFormats = $oaipmh->getOaipmh()->ListMetadataFormats->metadataFormat;
			foreach ($metadataFormats as $metadataFormat) {
				$metadataPrefix = (string) $metadataFormat->metadataPrefix;
				if (in_array($metadataPrefix, $maps)) {
					$availableMaps[$metadataPrefix] = $metadataPrefix;
				}
			}
    	} else {
    		if (in_array('oai_dc', $maps)) {
    			$availableMaps['oai_dc'] = 'oai_dc';
			}
		}
    	
    	// Get the sets from the data provider.
    	$baseUrl = $_POST['base_url'];
    	$requestArguments = array('verb' => 'ListSets');

		// If a resumption token exists, process it. For a data provider that 
		// uses a resumption token for sets, see: http://www.ajol.info/oai/
    	if (isset($_POST['resumption_token'])) {
    		$requestArguments['resumptionToken'] = $_POST['resumption_token'];
		}
		
    	$oaipmh = new OaipmhHarvester_Oaipmh($baseUrl, $requestArguments);
		$sets = $oaipmh->getOaipmh()->ListSets->set;
		
		// Set the resumption token, if any.
		if (isset($oaipmh->getOaipmh()->ListSets->resumptionToken)) {
			$resumptionToken = $oaipmh->getOaipmh()->ListSets->resumptionToken;
		} else {
			$resumptionToken = false;
		}
		
		// Set the variables to the view object.
		$this->view->availableMaps	 = $availableMaps;
    	$this->view->sets			 = $sets;
    	$this->view->resumptionToken = $resumptionToken;
    	$this->view->baseUrl		 = $baseUrl;
    }
    
    public function harvestAction()
    {
    	$baseUrl		= $_POST['base_url'];
    	$setSpec		= $_POST['set_spec'];
    	$setName		= $_POST['set_name'];
    	$setDescription	= $_POST['set_description'];
    	$metadataPrefix = $_POST['metadata_prefix'];
    	
    	// Insert the set.
    	$oaipmhHarvesterSet = new OaipmhHarvesterSet;
		$statusId = $this->getTable('OaipmhHarvesterSetStatus')
						 ->findIdByName('In Progress');
		
		$oaipmhHarvesterSet->status_id		 = $statusId;
		$oaipmhHarvesterSet->base_url		 = $baseUrl;
		$oaipmhHarvesterSet->set_spec		 = $setSpec;
		$oaipmhHarvesterSet->set_name		 = $setName;
		$oaipmhHarvesterSet->set_description = $setDescription;
		$oaipmhHarvesterSet->metadata_prefix = $metadataPrefix;
		$oaipmhHarvesterSet->initiated		 = date('Y:m:d H:i:s');
		$oaipmhHarvesterSet->save();
    	
    	// Set the command arguments.
    	$phpCommandPath	 = $this->_getPhpCommandPath();
    	$harvestFilePath = $this->_getHarvestFilePath($metadataPrefix);
    	$setId		     = escapeshellarg($oaipmhHarvesterSet->id);
    	
    	// Set the command and run the script in the background.
    	$command = "$phpCommandPath $harvestFilePath -s $setId";
    	//$this->_fork($command);
    	
    	$this->flashSuccess("Set \"$setSpec\" is being harvested using \"$metadataPrefix\". This may take a while. Please check below for status.");

    	$this->redirect->goto('index');
    	exit;
	}
	
	private function _getMaps()
	{
    	// Get the available OAI-PMH to Omeka maps, which should correspond to 
    	// OAI-PMH metadata formats.
    	$dir = new DirectoryIterator(OAIPMH_HARVESTER_MAPS_DIRECTORY);
    	$maps = array();
    	foreach ($dir as $dirEntry) {
    		if ($dirEntry->isDir() && !$dirEntry->isDot()) {
    			// The harvest.php file must exist in order to use the map. 
    			if (file_exists(OAIPMH_HARVESTER_MAPS_DIRECTORY 
    						  . DIRECTORY_SEPARATOR 
    						  . $dirEntry->getFilename() 
    						  . DIRECTORY_SEPARATOR 
    						  . 'harvest.php')) {
    				$maps[] = $dirEntry->getFilename();
    			}
			}
		}
		return $maps;
	}
	
	private function _getHarvestFilePath($metadataPrefix)
	{
		return OAIPMH_HARVESTER_MAPS_DIRECTORY
			 . DIRECTORY_SEPARATOR 
			 . $metadataPrefix 
			 . DIRECTORY_SEPARATOR 
			 . 'harvest.php';
	}
	
	// Get the path to the PHP CLI command. This does not account for servers 
	// without a PHP CLI or those with a different command name for PHP, such as 
	// "php5".
	private function _getPhpCommandPath()
	{
        $command = 'which php 2>&0';
        $lastLineOutput = exec($command, $output, $returnVar);
        return $returnVar == 0 ? trim($lastLineOutput) : '';
	}
    
    // Launch a low-priority background process, returning control to the 
    // foreground. See: http://www.php.net/manual/en/ref.exec.php#70135
    private function _fork($command) {
        exec("nice $command > /dev/null 2>&1 &");
    }
}