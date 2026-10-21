"""Capture the real app on an available 13-inch iPad simulator in Codemagic."""
import json
import pathlib
import subprocess
import time


def run(*args):
    return subprocess.check_output(args, text=True)


devices = json.loads(run('xcrun', 'simctl', 'list', 'devices', 'available', '--json'))
candidates = [
    d for runtime, group in devices['devices'].items()
    if 'iOS' in runtime
    for d in group
    if 'iPad' in d['name'] and ('13-inch' in d['name'] or '12.9-inch' in d['name'])
]
if not candidates:
    raise SystemExit('No 13-inch iPad simulator available. Check the Xcode image.')
device = candidates[-1]
udid = device['udid']
print('Capturing on ' + device['name'], flush=True)
if device['state'] != 'Booted':
    subprocess.run(['xcrun', 'simctl', 'boot', udid], check=True)
subprocess.run(['xcrun', 'simctl', 'bootstatus', udid, '-b'], check=True)
subprocess.run([
    'xcrun', 'simctl', 'status_bar', udid, 'override', '--time', '9:41',
    '--dataNetwork', 'wifi', '--wifiMode', 'active', '--wifiBars', '3',
    '--batteryState', 'charged', '--batteryLevel', '100',
], check=True)
subprocess.run(['xcrun', 'simctl', 'ui', udid, 'appearance', 'light'], check=True)
subprocess.run([
    'flutter', 'run', '--debug', '--no-resident', '-d', udid,
    '--dart-define=TOS_SCREENSHOTS=true',
    '--dart-define=TOS_BASE_URL=https://torneioldschool.it',
], check=True, timeout=1800)
output = pathlib.Path('build/app-store-screenshots')
output.mkdir(parents=True, exist_ok=True)
# Allow the real API requests and images to finish. Both captures are kept for review.
for name, delay in [('ipad-13-home.png', 30), ('ipad-13-home-later.png', 15)]:
    time.sleep(delay)
    target = str(output / name)
    subprocess.run(['xcrun', 'simctl', 'io', udid, 'screenshot', '--type=png', target], check=True)
    info = run('sips', '-g', 'pixelWidth', '-g', 'pixelHeight', target)
    print(info, flush=True)
    if not any(f'pixelWidth: {w}' in info and f'pixelHeight: {h}' in info
               for w, h in [(2064, 2752), (2048, 2732)]):
        raise SystemExit('Screenshot size does not match App Store iPad 13-inch requirements')
