import { chromium } from '@playwright/test';
import { createReadStream, statSync, writeFileSync } from 'node:fs';
import { createServer } from 'node:http';
import { resolve } from 'node:path';

const directory = resolve('artifacts/submission-20261020');
const file = `${directory}/ScopeTwin-demo-1080p.mp4`;
const size = statSync(file).size;
const server = createServer((request, response) => {
    if (request.url === '/demo.mp4') {
        const range = /bytes=(\d+)-(\d*)/.exec(request.headers.range ?? '');
        const start = range ? Number(range[1]) : 0;
        const end = range && range[2] ? Number(range[2]) : size - 1;
        response.writeHead(range ? 206 : 200, { 'Content-Type': 'video/mp4', 'Accept-Ranges': 'bytes', 'Content-Length': end-start+1, ...(range ? { 'Content-Range': `bytes ${start}-${end}/${size}` } : {}) });
        createReadStream(file, { start, end }).pipe(response);
    } else {
        response.setHeader('Content-Type', 'text/html');
        response.end(`<video id="video" src="/demo.mp4" controls width="1920" height="1080"></video><button id="play">Play checked video</button><script>
        const video=document.getElementById('video');const audio=new AudioContext();const analyser=audio.createAnalyser();audio.createMediaElementSource(video).connect(analyser);analyser.connect(audio.destination);window.peakRms=0;setInterval(()=>{const data=new Float32Array(analyser.fftSize);analyser.getFloatTimeDomainData(data);const rms=Math.sqrt(data.reduce((s,x)=>s+x*x,0)/data.length);window.peakRms=Math.max(window.peakRms,rms);},100);document.getElementById('play').onclick=async()=>{await audio.resume();video.playbackRate=4;await video.play();};
        </script>`);
    }
});
await new Promise((done) => server.listen(0, '127.0.0.1', done));
const browser = await chromium.launch({ channel: 'chrome' });
try {
    const page = await browser.newPage({ viewport: { width: 1920, height: 1120 } });
    await page.goto(`http://127.0.0.1:${server.address().port}/`);
    await page.getByRole('button', { name: 'Play checked video' }).click();
    await page.waitForTimeout(1500);
    console.log(JSON.stringify(await page.evaluate(() => {const v=document.getElementById('video');return {diagnostic:true,current:v.currentTime,paused:v.paused,ready:v.readyState,error:v.error?.code??null,codec:v.canPlayType('video/mp4; codecs="avc1.640028, mp4a.40.2"'),audio_state:typeof window.peakRms};})));
    await page.waitForFunction(() => document.getElementById('video').currentTime > 15, null, { timeout: 20000 });
    await page.screenshot({ path: `${directory}/playback-proof.png` });
    await page.waitForFunction(() => document.getElementById('video').ended || document.getElementById('video').error, null, { timeout: 90000 });
    const result = await page.evaluate(() => { const v=document.getElementById('video'); return { ended:v.ended,error:v.error?.code??null,duration:v.duration,width:v.videoWidth,height:v.videoHeight,peak_audio_rms:window.peakRms,playback_rate:v.playbackRate }; });
    if (!result.ended || result.error || result.width !== 1920 || result.height !== 1080 || result.duration < 180 || result.duration > 240 || result.peak_audio_rms < .005) throw new Error('Full browser playback/audio failed');
    writeFileSync(`${directory}/playback-validation.json`, JSON.stringify(result, null, 2));
    console.log(JSON.stringify(result));
} finally { await browser.close(); await new Promise((done) => server.close(done)); }
