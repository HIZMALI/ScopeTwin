param([Parameter(Mandatory=$true)][string]$FfmpegDirectory)
$ErrorActionPreference='Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)
$output=(Resolve-Path 'artifacts/submission-20261020').Path
$ffmpeg=Join-Path $FfmpegDirectory 'ffmpeg.exe'
$probe=Join-Path $FfmpegDirectory 'ffprobe.exe'
if(!(Test-Path -LiteralPath $ffmpeg) -or !(Test-Path -LiteralPath $probe)){throw 'Verified FFmpeg/ffprobe required'}
$timeline=Get-Content (Join-Path $output 'timeline.json') -Raw | ConvertFrom-Json
$seconds=$timeline.seconds.ToString('0.000000',[Globalization.CultureInfo]::InvariantCulture)
$video=Join-Path $output 'ScopeTwin-demo-1080p.mp4'
$candidate=Join-Path $output ('render-'+[guid]::NewGuid().ToString('N')+'.mp4')
$lock=[IO.File]::Open((Join-Path $output 'render.lock'),[IO.FileMode]::OpenOrCreate,[IO.FileAccess]::ReadWrite,[IO.FileShare]::None)
Push-Location $output
try {
 $renderLog=& $ffmpeg -y -hide_banner -loglevel warning -i raw/ScopeTwin-browser.webm -i narration.wav -vf "fps=30,scale=1920:1080,subtitles=ScopeTwin-demo.en.srt:force_style='FontName=Arial,FontSize=10,Outline=0.5,Shadow=0,MarginV=12,BackColour=&H99000000,BorderStyle=3'" -t $seconds -c:v libx264 -preset fast -crf 20 -pix_fmt yuv420p -c:a aac -b:a 160k -ar 48000 -ac 2 -af 'loudnorm=I=-16:TP=-1.5:LRA=11' -movflags +faststart -metadata title='ScopeTwin - Evaluate, Replay, Review' -metadata comment='Real local browser recording; synthetic data; no accepted AI result shown.' $candidate 2>&1
 $renderLog | Set-Content render-validation.txt
 if($LASTEXITCODE){throw 'MP4 rendering failed'}
 $json=& $probe -v error -show_format -show_streams -of json $candidate
 if($LASTEXITCODE){throw 'Media metadata probe failed'}
 $media=$json|ConvertFrom-Json
 $v=@($media.streams|Where-Object codec_type -eq video)[0];$a=@($media.streams|Where-Object codec_type -eq audio)[0]
 if($v.codec_name -ne 'h264' -or $v.width -ne 1920 -or $v.height -ne 1080 -or $v.r_frame_rate -ne '30/1' -or $a.codec_name -ne 'aac' -or [double]$media.format.duration -lt 180 -or [double]$media.format.duration -gt 240){throw 'Media delivery requirements failed'}
 $decodeLog=& $ffmpeg -v error -xerror -i $candidate -f null - 2>&1
 $decodeLog | Set-Content decode-validation.txt
 if($LASTEXITCODE){throw 'Complete video/audio decode failed'}
 & $ffmpeg -hide_banner -i $candidate -vn -af volumedetect -f null - 2>&1 | Set-Content audio-validation.txt
 if($LASTEXITCODE){throw 'Audio validation failed'}
 Move-Item -LiteralPath $candidate -Destination $video -Force
 $media.format.filename=$video
 $media | ConvertTo-Json -Depth 12 | Set-Content media-properties.json -Encoding utf8NoBOM
 Get-FileHash -LiteralPath $video -Algorithm SHA256 | Select-Object Hash | ConvertTo-Json | Set-Content video-sha256.json
 Write-Output ('MP4_RENDER=PASS; FULL_DECODE=PASS; H264=1920x1080_30FPS; AAC=STEREO_48KHZ; DURATION_SECONDS='+$media.format.duration)
} finally {Pop-Location;$lock.Dispose()}
