"""Join offline sentence audio and create captions from its measured durations."""
import json
import pathlib
import textwrap
import wave

root = pathlib.Path(__file__).resolve().parent.parent
output = root / 'artifacts' / 'submission-20261020'
scenes = json.loads((root / 'docs' / 'VIDEO_SCENES.json').read_text())
rate = 22050
frames = bytearray()
timeline = []
captions = []
position = 0.0

def silence(seconds):
    return bytes(round(seconds * rate) * 2)

def stamp(seconds):
    ms = round(seconds * 1000)
    return f'{ms//3600000:02}:{ms//60000%60:02}:{ms//1000%60:02},{ms%1000:03}'

for scene in scenes:
    chunks = []
    for i, sentence in enumerate(scene['sentences']):
        with wave.open(str(output / 'audio' / f'{scene["id"]}-{i}.wav'), 'rb') as source:
            assert source.getframerate() == rate and source.getnchannels() == 1 and source.getsampwidth() == 2
            chunk = source.readframes(source.getnframes())
        chunks.append((sentence, chunk, len(chunk) / (2 * rate)))
    required = 1.0 + sum(d for _, _, d in chunks) + .25 * (len(chunks)-1) + 1.0
    duration = max(scene['seconds'], required)
    start = position
    frames.extend(silence(1.0))
    position += 1.0
    for sentence, chunk, seconds in chunks:
        captions.append((position, position + seconds, sentence))
        frames.extend(chunk)
        position += seconds
        frames.extend(silence(.25))
        position += .25
    remaining = start + duration - position
    assert remaining >= 0
    frames.extend(silence(remaining))
    position = start + duration
    timeline.append({'id': scene['id'], 'start': start, 'seconds': duration})

assert 180 <= position <= 240, position
with wave.open(str(output / 'narration.wav'), 'wb') as destination:
    destination.setnchannels(1)
    destination.setsampwidth(2)
    destination.setframerate(rate)
    destination.writeframes(frames)
(output / 'timeline.json').write_text(json.dumps({'seconds': position, 'scenes': timeline}, indent=2))
srt = '\n\n'.join(f'{i+1}\n{stamp(a)} --> {stamp(b)}\n'+ '\n'.join(textwrap.wrap(text, 86)) for i, (a,b,text) in enumerate(captions))+'\n'
(output / 'ScopeTwin-demo.en.srt').write_text(srt, encoding='utf-8')
(output / 'narration.en.txt').write_text('\n\n'.join(' '.join(s['sentences']) for s in scenes))
print(json.dumps({'audio_seconds': position, 'caption_cues': len(captions), 'sample_rate': rate}))
