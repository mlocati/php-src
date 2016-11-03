<#
.SYNOPSIS
	Compile PHP.
.DESCRIPTION
	This Powershell script compiles (or recompiles) PHP for Windows, in 32 or 64 bits.
.PARAMETER Bits
	32 to built for 32-bit PCs, 64 to build for 64-bit PCs.
	By default, if an environment variable named "Platform" exists and evaluates to "x86" or "x64", we'll use it.
	Otherwise this value will be 32 if the current PC is 32-bit or 64 if the current PC is 64-bit.
	Subsequent calls to this script will re-use the previously number of bits (if not specified in the Bits parameter).
.NOTES
	Author: Michele Locati <mlocati@gmail.com>
#>

# Set-ExecutionPolicy -ExecutionPolicy Unrestricted -Scope Process -Force

param([Byte]$bits=0)

# Setup preferences variables
$ErrorActionPreference = "Stop"
$WarningPreference = "Stop"
$ConfirmPreference = "None"
$ProgressPreference = "SilentlyContinue"

# Configuration
New-Variable -Name 'SDK_REMOTE' -Option Constant -Value 'https://github.com/OSTC/php-sdk-binary-tools.git'
New-Variable -Name 'SDK_BRANCH' -Option Constant -Value 'new_binary_tools'

# Setup variables - Step 1
$scriptDirectory = Split-Path -Path $MyInvocation.MyCommand.Definition -Parent
$sourceDirectory = Split-Path -Path $scriptDirectory -Parent
$tempDirectory = Join-Path -Path $scriptDirectory -ChildPath "temp"
$sdkDirectory = Join-Path -Path $tempDirectory -ChildPath "sdk"
$lastBuiltBitsFile = Join-Path -Path $tempDirectory -ChildPath "last.bits"
$taskFile = Join-Path -Path $tempDirectory -ChildPath "task.cmd"

# Determine the bits of the last/previous build
$previousBits = 0
if (Test-Path -Path $lastBuiltBitsFile) {
	switch (Get-Content -LiteralPath $lastBuiltBitsFile -ReadCount 1) { 
		"32" {
			$previousBits = 32
		}
		"64" {
			$previousBits = 64
		}
	}
}

# Determine the bits of this build
if ($bits -eq 0) {
	if ($previousBits -eq 0) {
		if ($env:Platform -eq "x64") {
			$bits = 64
		} elseif ($env:Platform -eq "x86") {
			$bits = 32
		} elseif ($env:PROCESSOR_ARCHITECTURE -eq "AMD64") {
			$bits = 64
		} else {
			$bits = 32
		}
	} else {
		$bits = $previousBits
	}
}
switch ($bits) { 
	32 {
		$architectureName = "x86"
		$architectureName2 = "x86"
	}
	64 {
		$architectureName = "x64"
		$architectureName2 = "amd64"
	}
	default {
		Write-Host "Invalid number of bits: $bits"
		exit 1
	}
}

# Setup variables - Step 2
$sdkStarter = Join-Path -Path $sdkDirectory -ChildPath "phpsdk-vc14-$architectureName.bat"
$visualStudioRoot=$env:VS140COMNTOOLS
$visualStudioRoot = Split-Path -Path $visualStudioRoot -Parent
$visualStudioRoot = Split-Path -Path $visualStudioRoot -Parent
$vcvarsall = Join-Path -Path $visualStudioRoot -ChildPath "VC"
$vcvarsall = Join-Path -Path $vcvarsall -ChildPath "vcvarsall.bat"
$objOutDirectory = Join-Path -Path $tempDirectory -ChildPath "obj-$architectureName"
$outputDirectory = Join-Path -Path $scriptDirectory -ChildPath "out-$architectureName"

# Check to see if we need to configure and build, or only build
$configure = $true
if (Test-Path $objOutDirectory) {
	if (Test-Path $outputDirectory) {
		if ($bits -eq $previousBits) {
			$configure = $false
		}
	}
}

# Prepare the directory structure
if (!(Test-Path -Path $tempDirectory)) {
	New-Item -Path $tempDirectory -ItemType "directory" | Out-Null
}
if ($configure) {
	if (Test-Path -Path $lastBuiltBitsFile) {
		Remove-Item -LiteralPath $lastBuiltBitsFile
	}
	if (Test-Path -Path $objOutDirectory) {
		Remove-Item -Recurse -LiteralPath $objOutDirectory
	}
	if (Test-Path -Path $outputDirectory) {
		Remove-Item -Recurse -LiteralPath $outputDirectory
	}
}
if (!(Test-Path -Path $objOutDirectory)) {
	New-Item -Path $objOutDirectory -ItemType "directory" | Out-Null
}
if (!(Test-Path -Path $outputDirectory)) {
	New-Item -Path $outputDirectory -ItemType "directory" | Out-Null
}

# Checkout/update SDK
if (Test-Path -Path $sdkDirectory) {
	Write-Host "Fetching remote SDK repository"
	git --git-dir="$sdkDirectory\.git" --work-tree="$sdkDirectory" fetch --prune origin
	Write-Host "Checkout SDK repository branch"
	git --git-dir="$sdkDirectory\.git" --work-tree="$sdkDirectory" checkout --force -B $SDK_BRANCH remotes/origin/$SDK_BRANCH --
} else {
	Write-Host "Cloning remote SDK repository"
	git clone --branch $SDK_BRANCH $SDK_REMOTE "$sdkDirectory"
}

# Build the task script
$task = @"
@echo off

if "%APPVEYOR%" equ "True" rmdir /s /q C:\cygwin >NUL 2>NUL

call `"$vcvarsall`" $architectureName2
if errorlevel 1 exit /b 1

cd /D `"$sourceDirectory`"
if errorlevel 1 exit /b 1
"@
if ($configure) {
	$task = @"
$task
nmake clean >NUL 2>NUL
call buildconf.bat --force
if errorlevel 1 exit /b 1
call configure.bat ^
	--enable-snapshot-build ^
	--enable-debug-pack ^
	--enable-com-dotnet=shared ^
	--with-mcrypt=static ^
	--without-analyzer ^
	`"--enable-object-out-dir=$objOutDirectory`" ^
	`"--with-prefix=$outputDirectory`" ^
	`"--with-php-build=$dependenciesDirectory`" ^
	--with-mp=auto
if errorlevel 1 exit /b 1
echo $bits>`"$lastBuiltBitsFile`"

"@
}
$task = @"
$task

nmake /NOLOGO /S
if errorlevel 1 exit /b 1

nmake /NOLOGO /S install
if errorlevel 1 exit /b 1

exit /b 0
"@

# Executing the build script
if ($configure) {
	Write-Host "Configuring and compiling for $bits bits"
} else {
	Write-Host "Compiling for $bits bits"
}
$task | Out-File -FilePath $taskFile -Encoding "ascii"

$ErrorActionPreference = "SilentlyContinue"
& $sdkStarter "$taskFile"
$scriptExitCode = $LastExitCode
$ErrorActionPreference = "Stop"
Remove-Item -LiteralPath $taskFile
if ($scriptExitCode -ne 0) {
	Write-Host "Build script failed!"
	exit 1
}
