<?php //////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////

namespace Local;

use Nether\Common;

////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////

class KeyFileConf
extends Common\Prototype {

	public string
	$File;

	#[Common\Meta\PropertyListable]
	public ?string
	$Name = NULL;

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	public function
	GetDataArray():
	array {

		$Output = [];

		$Props = static::GetPropertiesWithAttribute(
			Common\Meta\PropertyListable::class
		);

		////////

		foreach($Props as $Key => $Val) {
			/** @var Common\Prototype\PropertyInfo $Val */
			$Output[$Val->Name] = $this->{$Val->Name};
		}

		////////

		return $Output;
	}

	public function
	GetDataJSON():
	string {

		$JSON = Common\Filters\Text::ReadableJSON(
			(object)$this->GetDataArray()
		);

		return $JSON;
	}

	public function
	Write():
	static {

		Common\Filesystem\Util::TryToWriteFile(
			$this->File,
			$this->GetDataJSON()
		);

		return $this;
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	static public function
	New(string $Filename, ?string $Name=NULL):
	static {

		$Output = new static;
		$Output->File = $Filename;
		$Output->Name = $Name;
		$Output->Write();

		return $Output;
	}

};
