<?php //////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////

$AppRoot = dirname(__FILE__, 2);

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
			?: $this->GetHostFile()
		);

		$Conf = Local\HostConf::FromFile($Path);

		Console\Elements\H1::New(
			Client: $this,
			Text: $Conf->Filename,
			Print: 2
		);

		Console\Elements\ListNamed::New(
			Client: $this,
			Items: (
				$Conf->Hosts->Map(
					fn(Local\HostItem $I)=> $I->IdentityFile)
				->Export()
			),
			Print: 2
		);

		return 0;
	}

	#[Console\Meta\Command('keys')]
	public function
	CmdKeys():
	int {

		$Path = (FALSE
			?: $this->GetOption('path')
			?: $this->GetHostFile()
		);

		Console\Elements\ListNamed::New(
			Client: $this,
			Items: [
				'Keys' => $this->GetKeyRoot()
			],
			Print: 2
		);

		$Keys = $this->FindInstalledKeys();

		Console\Elements\ListOrdered::New(
			Client: $this,
			Items: $Keys->Export(),
			Print: 2
		);

		return 0;
	}

	#[Console\Meta\Command('set')]
	#[Console\Meta\Arg('host')]
	#[Console\Meta\Error(1, 'no host specified')]
	public function
	CmdSet():
	int {

		$Path = (FALSE
			?: $this->GetOption('path')
			?: $this->GetHostFile()
		);

		$Host = $this->GetInput(1);

		////////

		if(!$Host)
		$this->Quit(1);

		$List = $this->FindInstalledKeys();
		$Conf = Local\HostConf::FromFile($Path);

		////////

		Console\Elements\H2::New(
			Client: $this,
			Text: 'Choose Key-Pair for Host',
			Print: 2
		);

		Console\Elements\ListOrdered::New(
			Client: $this,
			Items: $List->Export(),
			Print: 2
		);

		$Choice = ((int)$this->Prompt('Enter Number:', '>') - 1);

		if($Choice < 0) {
			$this->PrintStatusMuted('no selection made');
			return 0;
		}

		if(!$List->HasKey($Choice)) {
			$this->PrintStatusMuted('invalid selection');
			return 0;
		}

		$this->PrintStatusMuted(sprintf(
			'Selected: %s',
			$List->Get($Choice)
		));

		$Conf->Hosts[$Host] = new Local\HostItem([
			'IdentityFile' => $this->GetKeyFilePrivate($List->Get($Choice))
		]);

		$Conf->Write();

		return 0;
	}

	#[Console\Meta\Command('unset')]
	#[Console\Meta\Arg('host')]
	#[Console\Meta\Error(1, 'no host specified')]
	public function
	CmdUnset():
	int {

		$Path = (FALSE
			?: $this->GetOption('path')
			?: $this->GetHostFile()
		);

		$Host = $this->GetInput(1);

		////////

		if(!$Host)
		$this->Quit(1);

		$Conf = Local\HostConf::FromFile($Path);
		$Conf->Hosts->Remove($Host);
		$Conf->Write();

		return 0;
	}


	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	#[Common\Meta\Info('return path within user directory.')]
	protected function
	GetUserPath(string $To):
	string {

		$Root = (FALSE ?: getenv('HOME') ?: $this->GetOption('home'));
		$Path = Common\Filesystem\Util::Pathify($Root, $To);

		return $Path;
	}

	#[Common\Meta\Info('return path to managed key directory.')]
	protected function
	GetKeyRoot():
	string {

		if(Phar::Running())
		return Common\Filesystem\Util::Pathify(
			str_replace('phar://', '', dirname($this->AppRoot)),
			'keys'
		);

		return Common\Filesystem\Util::Pathify(
			$this->AppRoot,
			'keys'
		);
	}

	#[Common\Meta\Info('return path to a private key.')]
	protected function
	GetKeyFilePrivate(string $KName):
	string {

		return Common\Filesystem\Util::Pathify(
			$this->GetKeyRoot(),
			$KName,
			'key.priv'
		);
	}

	#[Common\Meta\Info('return path to a public key.')]
	protected function
	GetKeyFilePublic(string $KName):
	string {

		return Common\Filesystem\Util::Pathify(
			$this->GetKeyRoot(),
			$KName,
			'key.pub'
		);
	}

	#[Common\Meta\Info('return path to managed host configuration file.')]
	protected function
	GetHostFile():
	string {

		return Common\Filesystem\Util::Pathify(
			$this->GetKeyRoot(),
			'hosts.conf'
		);
	}

	#[Common\Meta\Info('check if a file looks like a private key.')]
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

	#[Common\Meta\Info('check if a file looks like a public key.')]
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

	#[Common\Meta\Info('return list of key-pair directories.')]
	public function
	FindInstalledKeys():
	Common\Datastore {

		$Index = Common\Filesystem\Indexer::DatastoreFromPath(
			$this->KeyRoot
		);

		($Index)
		->Filter(fn(string $P)=> is_dir($P))
		->Remap(
			fn(string $P)
			=> Common\Filesystem\Util::Basename($P)
		)
		->Sort()
		->Revalue();

		return $Index;
	}

	#[Common\Meta\Info('find paired keys within specified path.')]
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

	#[Common\Meta\Info('find a public key that pairs with a public key.')]
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

	#[Common\Meta\Info('copy key pair into managed directory.')]
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
		Common\Filesystem\Util::Chmod($KeyPriv, 0600);

		copy($Pair->Public, $KeyPub);
		Common\Filesystem\Util::Chmod($KeyPub, 0644);

		$Conf = Local\KeyFileConf::New(
			Filename: $KeyFile,
			Name: $Name
		);

		return;
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	protected function
	GetPharFiles():
	Common\Datastore {

		$Index = parent::GetPharFiles();

		$Index->Push('core');

		return $Index;
	}

	protected function
	GetPharFileFilters():
	Common\Datastore {

		$Output = parent::GetPharFileFilters();

		$Output->Push(fn(string $Path)=> !str_starts_with($Path, 'vendor/monolog'));
		$Output->Push(fn(string $Path)=> !str_starts_with($Path, 'vendor/dealerdirect'));
		$Output->Push(fn(string $Path)=> !str_starts_with($Path, 'vendor/fileeye'));
		$Output->Push(fn(string $Path)=> !str_starts_with($Path, 'vendor/squizlabs'));
		$Output->Push(fn(string $Path)=> !str_starts_with($Path, 'vendor/fileeye'));
		$Output->Push(fn(string $Path)=> !str_starts_with($Path, 'vendor/psr'));
		$Output->Push(fn(string $Path)=> !str_starts_with($Path, 'vendor/aws'));
		$Output->Push(fn(string $Path)=> !str_starts_with($Path, 'vendor/google'));

		return $Output;
	}

};

////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////

exit(Keybox::Realboot([
	'AppRoot' => $AppRoot
]));
