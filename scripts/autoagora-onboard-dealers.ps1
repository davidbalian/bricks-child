[CmdletBinding(DefaultParameterSetName = 'Run')]
param(
    [Parameter(ParameterSetName = 'Run', Mandatory = $true)]
    [string] $InputFile,

    [Parameter(ParameterSetName = 'Run')]
    [switch] $Commit,

    [Parameter(ParameterSetName = 'Configure', Mandatory = $true)]
    [switch] $Configure,

    [Parameter(ParameterSetName = 'Reveal', Mandatory = $true)]
    [string] $RevealCredentialsFile,

    [Parameter(ParameterSetName = 'State', Mandatory = $true)]
    [switch] $ListExisting,

    [string] $SiteUrl = 'https://autoagora.cy',

    [string] $AuthFile = (Join-Path $env:LOCALAPPDATA 'AutoAgora\dealer-onboarding-auth.clixml'),

    [string] $CredentialOutputDirectory = [Environment]::GetFolderPath('MyDocuments')
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function ConvertFrom-DealerSecureString {
    param([Security.SecureString] $SecureValue)
    $pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($SecureValue)
    try {
        return [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)
    }
    finally {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer)
    }
}

if ($PSCmdlet.ParameterSetName -eq 'Configure') {
    $authDirectory = Split-Path -Parent $AuthFile
    if (-not (Test-Path -LiteralPath $authDirectory)) {
        New-Item -ItemType Directory -Path $authDirectory -Force | Out-Null
    }
    $credential = Get-Credential -Message 'Enter the WordPress administrator username and its Application Password'
    $credential | Export-Clixml -LiteralPath $AuthFile -Force
    Write-Host "Saved Windows-user-encrypted API credentials to $AuthFile"
    exit 0
}

if ($PSCmdlet.ParameterSetName -eq 'Reveal') {
    $saved = Import-Clixml -LiteralPath $RevealCredentialsFile
    $saved | ForEach-Object {
        [PSCustomObject]@{
            Dealership = $_.Dealership
            Username = $_.Login.UserName
            Password = $_.Login.GetNetworkCredential().Password
            Phone = $_.Phone
            ProfileId = $_.ProfileId
        }
    } | Format-Table -AutoSize
    exit 0
}

if (-not (Test-Path -LiteralPath $AuthFile)) {
    throw "Authentication file not found. Run this script once with -Configure."
}

$credential = Import-Clixml -LiteralPath $AuthFile
if ($credential -isnot [Management.Automation.PSCredential]) {
    throw 'The authentication file does not contain a PowerShell credential.'
}

if ($PSCmdlet.ParameterSetName -eq 'State') {
    $applicationPassword = ConvertFrom-DealerSecureString $credential.Password
    try {
        $basicValue = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes("$($credential.UserName):$applicationPassword"))
        $headers = @{ Authorization = "Basic $basicValue"; 'Cache-Control' = 'no-store' }
        $endpoint = $SiteUrl.TrimEnd('/') + '/wp-json/autoagora/v1/dealers/onboarding-state'
        $state = Invoke-RestMethod -Method Get -Uri $endpoint -Headers $headers
    }
    finally {
        $applicationPassword = $null
        $basicValue = $null
    }

    $state.accounts | ForEach-Object {
        [PSCustomObject]@{
            UserId = $_.user_id
            Dealership = $_.name
            Phone = $_.phone
            SyncProfiles = (@($_.sync_profiles | ForEach-Object { $_.profile_id }) -join ', ')
        }
    } | Format-Table -AutoSize
    exit 0
}

if (-not (Test-Path -LiteralPath $InputFile)) {
    throw "Dealer input file not found: $InputFile"
}

$inputData = Get-Content -LiteralPath $InputFile -Raw | ConvertFrom-Json
$dealers = if ($inputData -is [array]) { $inputData } else { $inputData.dealers }
if ($null -eq $dealers) {
    throw 'Input JSON must be an array of dealers or an object with a dealers array.'
}

$mode = if ($Commit) { 'commit' } else { 'validate' }
$requestBody = @{
    mode = $mode
    dealers = @($dealers)
    client_request_id = [guid]::NewGuid().ToString()
} | ConvertTo-Json -Depth 10

$applicationPassword = ConvertFrom-DealerSecureString $credential.Password
try {
    $basicValue = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes("$($credential.UserName):$applicationPassword"))
    $headers = @{
        Authorization = "Basic $basicValue"
        'Cache-Control' = 'no-store'
    }
    $endpoint = $SiteUrl.TrimEnd('/') + '/wp-json/autoagora/v1/dealers/onboard'
    $response = Invoke-RestMethod -Method Post -Uri $endpoint -Headers $headers -ContentType 'application/json; charset=utf-8' -Body $requestBody
}
finally {
    $applicationPassword = $null
    $basicValue = $null
}

if (-not $Commit) {
    Write-Host "Validation passed for $(@($response.dealers).Count) dealer(s). No changes were made."
    $response.dealers | Select-Object name, phone, profile_id, location_complete | Format-Table -AutoSize
    Write-Host 'Run the same command with -Commit to create the accounts and disabled dry-run sync profiles.'
    exit 0
}

$credentialRows = foreach ($dealer in @($response.dealers)) {
    $securePassword = ConvertTo-SecureString -String $dealer.password -AsPlainText -Force
    [PSCustomObject]@{
        Dealership = $dealer.name
        Login = [Management.Automation.PSCredential]::new($dealer.username, $securePassword)
        Phone = $dealer.phone
        UserId = $dealer.user_id
        ProfileId = $dealer.profile_id
        BazarakiUrl = $dealer.bazaraki_url
        CreatedAt = [DateTime]::UtcNow
    }
}

if (-not (Test-Path -LiteralPath $CredentialOutputDirectory)) {
    New-Item -ItemType Directory -Path $CredentialOutputDirectory -Force | Out-Null
}
$timestamp = Get-Date -Format 'yyyy-MM-dd-HHmmss'
$credentialFile = Join-Path $CredentialOutputDirectory "AutoAgora Dealer Credentials $timestamp.clixml"
$credentialRows | Export-Clixml -LiteralPath $credentialFile -Force

$response.dealers | ForEach-Object { $_.password = $null }
$credentialRows = $null
[GC]::Collect()

Write-Host "Created $(@($response.dealers).Count) dealership account(s)."
Write-Host 'Their Bazaraki profiles are disabled, excluded from runs, and set to dry-run.'
Write-Host "Passwords were not printed. They are Windows-user-encrypted at $credentialFile"
Write-Host "To view them: .\scripts\autoagora-onboard-dealers.ps1 -RevealCredentialsFile '$credentialFile'"
