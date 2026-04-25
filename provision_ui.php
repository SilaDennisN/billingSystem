<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Router Provisioning</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .header p {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .content {
            padding: 30px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        .controls {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .step-group {
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 15px;
            background: #fafafa;
        }
        
        .step-group h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .buttons {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        button {
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        button.primary {
            background: #667eea;
            color: white;
        }
        
        button.primary:hover {
            background: #5568d3;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        button.secondary {
            background: #e8e8e8;
            color: #333;
        }
        
        button.secondary:hover {
            background: #d8d8d8;
        }
        
        button.success {
            background: #48bb78;
            color: white;
        }
        
        button.success:hover {
            background: #38a169;
        }
        
        button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .output {
            background: #1e1e1e;
            color: #d4d4d4;
            font-family: 'Monaco', 'Courier New', monospace;
            font-size: 12px;
            padding: 20px;
            border-radius: 6px;
            overflow-y: auto;
            max-height: 600px;
            line-height: 1.5;
        }
        
        .output-line {
            margin-bottom: 8px;
            padding: 6px 10px;
            border-left: 3px solid transparent;
            border-radius: 2px;
        }
        
        .output-line.running {
            border-left-color: #4a90e2;
            background: rgba(74, 144, 226, 0.1);
            color: #4a90e2;
        }
        
        .output-line.ok {
            border-left-color: #48bb78;
            background: rgba(72, 187, 120, 0.1);
            color: #48bb78;
        }
        
        .output-line.error {
            border-left-color: #f56565;
            background: rgba(245, 101, 101, 0.1);
            color: #f56565;
        }
        
        .step-indicator {
            display: inline-block;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            margin-right: 8px;
            vertical-align: middle;
            font-weight: bold;
            font-size: 10px;
            line-height: 16px;
            text-align: center;
            color: white;
        }
        
        .step-indicator.running {
            background: #4a90e2;
        }
        
        .step-indicator.ok {
            background: #48bb78;
        }
        
        .step-indicator.error {
            background: #f56565;
        }
        
        @media (max-width: 1000px) {
            .content {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>🚀 Router Provisioning Control Panel</h1>
        <p>Step-by-step router configuration via RouterOS API</p>
    </div>
    
    <div class="content">
        <div class="controls">
            
            <div class="step-group">
                <h3>📍 Router Selection</h3>
                <input type="text" id="router_id" placeholder="Enter router_id" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            
            <div class="step-group">
                <h3>🔧 Firewall Rules</h3>
                <div class="buttons">
                    <button class="secondary" onclick="runStep('firewall-input')">INPUT Chain</button>
                    <button class="secondary" onclick="runStep('firewall-forward')">FORWARD Chain</button>
                    <button class="secondary" onclick="runStep('firewall-nat')">NAT (Masquerade)</button>
                </div>
            </div>
            
            <div class="step-group">
                <h3>🌐 Network & Services</h3>
                <div class="buttons">
                    <button class="secondary" onclick="runStep('dns')">Configure DNS</button>
                    <button class="secondary" onclick="runStep('identity')">Set Identity</button>
                    <button class="secondary" onclick="runStep('bridge')">Create Bridge</button>
                    <button class="secondary" onclick="runStep('bridge-ip')">Bridge IP</button>
                </div>
            </div>
            
            <div class="step-group">
                <h3>⚡ All-in-One</h3>
                <button class="success" onclick="runStep('all')" style="width: 100%; height: 45px; font-size: 14px;">
                    ✓ Provision All (Basics)
                </button>
            </div>
            
            <div style="background: #fffbeb; padding: 15px; border-radius: 4px; border-left: 4px solid #f59e0b;">
                <strong style="color: #92400e;">💡 Next:</strong>
                <p style="font-size: 13px; color: #78350f; margin-top: 5px;">After basics, we'll add Hotspot + PPPoE</p>
            </div>
            
        </div>
        
        <div>
            <h3 style="margin-bottom: 15px;">📊 Output & Status</h3>
            <div class="output" id="output"></div>
            <button class="secondary" onclick="clearOutput()" style="width: 100%; margin-top: 10px;">Clear Output</button>
        </div>
    </div>
</div>

<script>
    const routerId = () => document.getElementById('router_id').value;
    const output = document.getElementById('output');
    
    function clearOutput() {
        output.innerHTML = '';
    }
    
    function addLine(step, status, message) {
        const line = document.createElement('div');
        line.className = `output-line ${status}`;
        
        let icon = '⊙';
        if (status === 'ok') icon = '✓';
        if (status === 'error') icon = '✕';
        
        line.innerHTML = `<span class="step-indicator ${status}">${icon}</span> <strong>[${step}]</strong> ${message}`;
        output.appendChild(line);
        output.scrollTop = output.scrollHeight;
    }
    
    function runStep(step) {
        const rid = routerId();
        if (!rid) {
            alert('Please enter router_id first');
            return;
        }
        
        clearOutput();
        addLine('provision', 'running', `Starting step: ${step}...`);
        
        const eventSource = new EventSource(`provision.php?router_id=${rid}&step=${step}`);
        
        eventSource.onmessage = (event) => {
            const data = JSON.parse(event.data);
            addLine(data.step, data.status, data.message);
            
            if (data.status === 'ok' && data.step === 'done') {
                setTimeout(() => eventSource.close(), 500);
            }
        };
        
        eventSource.onerror = (err) => {
            addLine('error', 'error', 'Connection lost or error occurred');
            eventSource.close();
        };
    }
    
    // Load router_id from URL if present
    const urlParams = new URLSearchParams(window.location.search);
    const rid = urlParams.get('router_id');
    if (rid) {
        document.getElementById('router_id').value = rid;
    }
</script>

</body>
</html>
