<?php ##########################################################################
################################################################################

namespace Local;

use Nether\Common;

################################################################################
################################################################################

class HostConf
extends Common\Prototype {

	public string
	$Filename;

	#[Common\Meta\PropertyObjectify]
	public array|Common\Datastore
	$Hosts = [];

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	public function
	Read(?string $Filename=NULL):
	static {

		if($Filename)
		$this->Filename = $Filename;

		////////

		$Lines = Common\Filesystem\Util::TryToReadFileLines($this->Filename);
		$Lines->Remap(fn(string $L)=> trim($L));

		$Host = NULL;
		$Line = NULL;
		$Info = NULL;

		foreach($Lines as $Line) {

			$Info = NULL;

			////////

			if(preg_match('/^Host (.+?)$/', $Line, $Info)) {
				$Host = $Info[1];
				$this->Hosts[$Host] = new HostItem;
				continue;
			}

			if(!$Host)
			continue;

			////////

			if(preg_match('/^IdentityFile ["]?(.+?)["]?$/', $Line, $Info)) {
				$this->Hosts[$Host]->IdentityFile = $Info[1];
				continue;
			}

		}

		return $this;
	}

	public function
	Write(?string $Filename=NULL):
	static {

		if($Filename)
		$this->Filename = $Filename;

		$Buffer = new Common\Overbuffer;

		$Buffer->Start();
		foreach($this->Hosts as $Key => $Val) {
			/** @var HostItem $Val */
			printf("Host %s%s", $Key, PHP_EOL);
			printf("	IdentityFile %s%s", $Val->IdentityFile, PHP_EOL);
			echo PHP_EOL;
		}
		$Buffer->Stop();

		file_put_contents($this->Filename, $Buffer->Get());

		return $this;
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	static public function
	FromFile(string $Filename):
	static {

		$Output = new static;
		$Output->Read($Filename);

		return $Output;
	}

};
