[CmdletBinding(DefaultParameterSetName = 'Run')]
param(
    [Parameter(ParameterSetName = 'Run', Mandatory = $true)]
    [string] $InputFile,

    [Parameter(ParameterSetName = 'Run')]
    [switch] $Commit,

    [Parameter(ParameterSetName = 'Run')]
    [switch] $UpdateLocations,

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

function Invoke-AutoAgoraOnboardingApi {
    param(
        [Parameter(Mandatory = $true)]
        [ValidateSet('GET', 'POST')]
        [string] $Method,

        [Parameter(Mandatory = $true)]
        [string] $Uri,

        [Parameter(Mandatory = $true)]
        [string] $Token,

        [string] $Body = ''
    )

    $curl = Get-Command 'curl.exe' -ErrorAction SilentlyContinue
    if (-not $curl) {
        throw "Windows curl.exe is required because the hosting firewall blocks PowerShell's built-in HTTP client."
    }

    $arguments = @(
        '--silent',
        '--show-error',
        '--fail-with-body',
        '--connect-timeout', '15',
        '--max-time', '120',
        '--request', $Method,
        '--user-agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AutoAgoraDealerOnboarding/1.0',
        '--referer', 'https://autoagora.cy/',
        '--variable', '%AUTOAGORA_ONBOARDING_TOKEN_PROCESS',
        '--expand-header', 'X-AutoAgora-Onboarding-Token: {{AUTOAGORA_ONBOARDING_TOKEN_PROCESS}}',
        '--header', 'Accept: application/json'
    )
    if ($Method -eq 'POST') {
        $arguments += @('--header', 'Content-Type: application/json; charset=utf-8', '--data-binary', '@-')
    }
    $arguments += $Uri

    $transportDirectory = Join-Path $env:LOCALAPPDATA 'AutoAgora\curl-runtime'
    if (-not (Test-Path -LiteralPath $transportDirectory)) {
        New-Item -ItemType Directory -Path $transportDirectory -Force | Out-Null
    }

    [Environment]::SetEnvironmentVariable('AUTOAGORA_ONBOARDING_TOKEN_PROCESS', $Token, 'Process')
    Push-Location -LiteralPath $transportDirectory
    try {
        $raw = if ($Method -eq 'POST') {
            $Body | & $curl.Source @arguments
        }
        else {
            & $curl.Source @arguments
        }
    }
    finally {
        Pop-Location
        [Environment]::SetEnvironmentVariable('AUTOAGORA_ONBOARDING_TOKEN_PROCESS', $null, 'Process')
    }
    if ($LASTEXITCODE -ne 0) {
        throw "AutoAgora API request failed with curl exit code $LASTEXITCODE. $raw"
    }

    return $raw | ConvertFrom-Json
}

if ($PSCmdlet.ParameterSetName -eq 'Configure') {
    $authDirectory = Split-Path -Parent $AuthFile
    if (-not (Test-Path -LiteralPath $authDirectory)) {
        New-Item -ItemType Directory -Path $authDirectory -Force | Out-Null
    }
    $secureToken = Read-Host 'Paste the dedicated AutoAgora dealer-onboarding token' -AsSecureString
    $credential = [Management.Automation.PSCredential]::new('autoagora-dealer-onboarding', $secureToken)
    $credential | Export-Clixml -LiteralPath $AuthFile -Force
    Write-Host "Saved the Windows-user-encrypted onboarding token to $AuthFile"
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
    $apiToken = ConvertFrom-DealerSecureString $credential.Password
    try {
        $endpoint = $SiteUrl.TrimEnd('/') + '/wp-json/autoagora/v1/dealers/onboarding-state'
        $state = Invoke-AutoAgoraOnboardingApi -Method GET -Uri $endpoint -Token $apiToken
    }
    finally {
        $apiToken = $null
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

$apiToken = ConvertFrom-DealerSecureString $credential.Password
try {
    $endpoint = $SiteUrl.TrimEnd('/') + '/wp-json/autoagora/v1/dealers/onboard'
    if ($UpdateLocations) {
        $endpoint = $SiteUrl.TrimEnd('/') + '/wp-json/autoagora/v1/dealers/locations'
    }
    $response = Invoke-AutoAgoraOnboardingApi -Method POST -Uri $endpoint -Token $apiToken -Body $requestBody
}
finally {
    $apiToken = $null
}

if ($UpdateLocations) {
    Write-Host "Location $mode succeeded for $(@($response.dealers).Count) dealer(s)."
    $response.dealers | Select-Object user_id, name, profile_id, location | Format-Table -AutoSize
    exit 0
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
