param([string]$OutputDirectory='artifacts/submission-20261020')
$ErrorActionPreference='Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)
Add-Type -AssemblyName System.Speech
$audio=Join-Path $OutputDirectory 'audio'
New-Item -ItemType Directory -Path $audio -Force | Out-Null
$speaker=New-Object System.Speech.Synthesis.SpeechSynthesizer
$speaker.SelectVoice('Microsoft Zira Desktop')
$speaker.Rate=0
$format=New-Object System.Speech.AudioFormat.SpeechAudioFormatInfo(22050,[System.Speech.AudioFormat.AudioBitsPerSample]::Sixteen,[System.Speech.AudioFormat.AudioChannel]::Mono)
try {
 $scenes=Get-Content docs/VIDEO_SCENES.json -Raw | ConvertFrom-Json
 foreach($scene in $scenes){
  for($i=0;$i -lt $scene.sentences.Count;$i++){
   $path=[IO.Path]::GetFullPath((Join-Path $audio ($scene.id+'-'+$i+'.wav')))
   $speaker.SetOutputToWaveFile($path,$format)
   $speaker.Speak($scene.sentences[$i])
   $speaker.SetOutputToNull()
  }
 }
} finally {$speaker.Dispose()}
Write-Output 'OFFLINE_ENGLISH_NARRATION=GENERATED; VOICE=MICROSOFT_ZIRA; NO_PAID_API'
