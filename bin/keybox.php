<?php //////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////

$AppRoot = dirname(__FILE__, 2);

if(Phar::Running()) {
	$AppRoot = dirname(__FILE__);
}

require(sprintf('%s/vendor/autoload.php', $AppRoot));

use Nether\Console;
use Nether\Common;

////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////

#[Console\Meta\Application('Keybox', '1.0.0-dev', Phar: 'keybox.phar')]
class Keybox
extends Console\Client {

	public string
	$AppRoot;

	public string
	$KeyRoot;

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	protected function
	OnPrepare():
	void {

		parent::OnPrepare();

		////////

		$this->AppRoot = $this->GetOption('AppRoot');
		$this->KeyRoot = $this->GetKeyRoot();

		return;
	}

	protected function
	OnReady():
	void {

		parent::OnReady();

		////////

		if(!is_dir($this->KeyRoot))
		Common\Filesystem\Util::MkDir($this->KeyRoot);

		return;
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	#[Console\Meta\Command('import')]
	#[Console\Meta\Option('path', TRUE, 'Path of .ssh to import.')]
	public function
	CmdImport():
	int {

		$Path = (FALSE
			?: $this->GetOption('path')
			?: $this->GetUserPath('.ssh')
		);

		if(!is_dir($Path))
		$this->Quit(1);

		////////

		$Found = $this->FindKeyFilePairs($Path);
		$Found->Each(function(Local\KeyFilePair $Pair) {

			$Name = NULL;
			$Force = FALSE;

			////////

			Console\Elements\ListNamed::New(
				Client: $this,
				Items: [
					'Private' => $Pair->Private,
					'Public'  => $Pair->Public
				],
				Print: 2
			);

			$Name = $this->Prompt(
				'Key Pair Name (blank to skip):',
				'>'
			);

			if(!$Name) {
				$this->PrintStatusMuted('Skipped');
				return;
			}

			////////

			InstallKeyPair: {{{
				try {
					$this->InstallKeyPair($Name, $Pair, $Force);
					$this->PrintStatusMuted('Done');
				}
				catch(Common\Error\DirExists $Err) {
					$Force = $this->PromptBool(
						'Key Pair Exists. Overwrite? [y/n]',
						'>'
					);

					if(!$Force) {
						$this->PrintStatusMuted('Skipped');
						return;
					}

					goto InstallKeyPair;
				}
			}}};

			return;
		});

		return 0;
	}

	#[Console\Meta\Command('hosts')]
	public function
	CmdHosts():
	int {

		$Path = (FALSE
			?: $this->GetOption('path')
			?: Common\Filesystem\Util::Pathify(
				$this->GetUserPath('.ssh'),
				'keybox.conf'
			)
		);

		Common\Dump::Var($Path);

		return 0;
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	protected function
	GetUserPath(string $To):
	string {

		$Root = (FALSE ?: getenv('HOME') ?: $this->GetOption('home'));
		$Path = Common\Filesystem\Util::Pathify($Root, $To);

		return $Path;
	}

	protected function
	GetKeyRoot():
	string {

		return Common\Filesystem\Util::Pathify(
			$this->AppRoot,
			'keys'
		);
	}

	protected function
	IsFilePrivateKey(string $File):
	bool {

		$Data = NULL;

		////////

		if(!is_file($File))
		return FALSE;

		if(!is_readable($File))
		return FALSE;

		$Data = file_get_contents($File);

		if(!str_starts_with($Data, '-----BEGIN '))
		return FALSE;

		////////

		return TRUE;
	}

	protected function
	IsFilePublicKey(string $File):
	bool {

		$Data = NULL;

		////////

		if(!is_file($File))
		return FALSE;

		if(!is_readable($File))
		return FALSE;

		$Data = file_get_contents($File);

		if(!str_starts_with($Data, 'ssh-'))
		return FALSE;

		////////

		return TRUE;
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	public function
	FindKeyFilePairs(string $Path):
	Common\Datastore {

		$Index = Common\Filesystem\Indexer::DatastoreFromPath($Path);
		$Pairs = Common\Datastore::FromArray([]);
		$Match = NULL;

		////////

		foreach($Index as $File) {
			if(!$this->IsFilePrivateKey($File))
			continue;

			$Match = $this->FindMatchingPublicKey($File);

			if(!$Match)
			continue;

			$Pairs->Push(Local\KeyFilePair::FromPair(
				$File, $Match
			));

			continue;
		}

		return $Pairs;
	}

	public function
	FindMatchingPublicKey(string $File):
	?string {

		$Basename = Common\Filesystem\Util::Basename($File);
		$Found = sprintf('%s.pub', $File);

		if($this->IsFilePublicKey($Found))
		return $Found;

		////////

		if(str_contains($Basename, '.'))
		$Found = Common\Filesystem\Util::ReplaceFileExtension($File, 'pub');

		if($this->IsFilePublicKey($Found))
		return $Found;

		////////

		return NULL;
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	protected function
	InstallKeyPair(string $Name, Local\KeyFilePair $Pair, bool $Force=FALSE):
	void {

		$Key = Common\Filters\Text::SlottableKey($Name);
		$Root = Common\Filesystem\Util::Pathify($this->KeyRoot, $Key);
		$KeyFile = Common\Filesystem\Util::Pathify($Root, 'key.json');
		$KeyPriv = Common\Filesystem\Util::Pathify($Root, 'key.priv');
		$KeyPub = Common\Filesystem\Util::Pathify($Root, 'key.pub');

		////////

		if(is_dir($Root) && !$Force)
		throw new Common\Error\DirExists($Root);

		////////

		Common\Filesystem\Util::MkDir($Root);
		copy($Pair->Private, $KeyPriv);
		copy($Pair->Public, $KeyPub);

		$Conf = Local\KeyFileConf::New(
			Filename: $KeyFile,
			Name: $Name
		);

		return;
	}

};

////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////

exit(Keybox::Realboot([
	'AppRoot' => $AppRoot
]));
