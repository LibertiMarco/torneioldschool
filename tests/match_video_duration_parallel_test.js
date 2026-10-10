'use strict';
const http = require('http');
const {spawn} = require('child_process');
const assert = require('assert').strict;
const path = require('path');

// Real cURL multi transfers against a local, delayed CDN fixture; no social accounts involved.
const movie = Buffer.alloc(36);
movie.writeUInt32BE(36,0);movie.write('moov',4);movie.writeUInt32BE(28,8);movie.write('mvhd',12);
movie.writeUInt32BE(1000,28);movie.writeUInt32BE(30000,32);
let active = 0, peak = 0;
const server = http.createServer((req,res) => {
    active++;peak = Math.max(peak,active);
    setTimeout(() => {
        active--;
        if (req.url.includes('bad')) {res.writeHead(503);res.end('Unavailable');return;}
        if (req.url.includes('redirect')) {res.writeHead(302,{Location:'https://example.invalid/'});res.end();return;}
        if (req.url.includes('big') || req.url.includes('ignored')) {
            res.writeHead(200,{'Content-Type':'video/mp4'});res.end(Buffer.concat([movie,Buffer.alloc(2*1024*1024)]));return;
        }
        if (req.url.includes('wrongrange')) {res.writeHead(206,{'Content-Range':'bytes 1-36/37'});res.end(movie);return;}
        assert.equal(req.headers.range,'bytes=0-65535');
        res.writeHead(206,{'Content-Range':'bytes 0-35/36','Content-Type':'video/mp4'});res.end(movie);
    },200);
});
server.listen(0,'127.0.0.1',async () => {
    const port = server.address().port;
    const php = process.env.PHP_BINARY || 'C:/xampp/php/php.exe';
    const file = path.resolve(__dirname,'../includi/match_video_duration.php').replace(/\\/g,'/');
    const program = `require $argv[1];
        $factory = function($url,$offset) {
            [$ch,$response] = video_sync_duration_handle($url,$offset);
            curl_setopt($ch,CURLOPT_URL,'http://127.0.0.1:${port}/'.basename($url));
            curl_setopt($ch,CURLOPT_PROTOCOLS,CURLPROTO_HTTP);
            return [$ch,$response];
        };
        $requests = [];
        for ($i=0;$i<8;$i++) $requests[$i] = ['url'=>'https://video.fbcdn.net/'.$i,'offset'=>0];
        $start = microtime(true);
        foreach ($requests as $request) video_sync_duration_ranges([$request],$factory);
        $serial = microtime(true)-$start;
        $start = microtime(true);$data = video_sync_duration_ranges($requests,$factory);$parallel = microtime(true)-$start;
        foreach ($data as $bytes) {$probe = ['offset'=>0];if (video_sync_mp4_duration_step($bytes,$probe) !== 30.0) throw new Exception('Duration lost');}
        $requests[3]['url'] = 'https://video.fbcdn.net/bad';$data = video_sync_duration_ranges($requests,$factory);
        if ($data[3] !== null || count(array_filter($data,'is_string')) !== 7) throw new Exception('Failure isolation lost');
        $edge = video_sync_duration_ranges([
            'big'=>['url'=>'https://video.fbcdn.net/big','offset'=>0],
            'ignored'=>['url'=>'https://video.fbcdn.net/ignored','offset'=>1024],
            'wrongrange'=>['url'=>'https://video.fbcdn.net/wrongrange','offset'=>0],
            'redirect'=>['url'=>'https://video.fbcdn.net/redirect','offset'=>0]
        ],$factory);
        if (strlen($edge['big']) !== VIDEO_SYNC_MP4_CHUNK || $edge['ignored'] !== null || $edge['wrongrange'] !== null || $edge['redirect'] !== null) throw new Exception('Transfer bounds or redirect validation lost');
        echo json_encode(['serial'=>$serial,'parallel'=>$parallel]);`;
    try {
        const result = await new Promise((resolve,reject) => {
            const proc = spawn(php,['-r',program,file],{windowsHide:true});
            let stdout = '',stderr = '';
            proc.stdout.on('data',chunk => stdout += chunk);proc.stderr.on('data',chunk => stderr += chunk);
            proc.on('error',reject);proc.on('close',code => code === 0 && !stderr ? resolve(JSON.parse(stdout)) : reject(new Error(stderr || `PHP exit ${code}`)));
        });
        assert.equal(peak,8,'Transfers did not run concurrently');
        assert(result.parallel < result.serial*0.65,'Parallel transfers were serialized');
        console.log(`PASS: 8 actual parallel cURL probes; ${result.serial.toFixed(2)}s serial → ${result.parallel.toFixed(2)}s parallel; failure isolation, 64 KiB cap, Range and redirect validation`);
    } catch (error) {console.error(error);process.exitCode = 1;}
    finally {server.close();}
});
