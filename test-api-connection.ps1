# Test CodexFlow API Connection
# Usage: .\test-api-connection.ps1

$apiKey = "cf_diUF9oye95rIOJ5BLoao1VKuYGjFyXauOeJFf4qs"
$baseUrl = "https://api.codexflow.dev"

Write-Host "=== CodexFlow API Connection Test ===" -ForegroundColor Cyan
Write-Host ""

# Test 1: Health Check
Write-Host "1. Testing health endpoint..." -ForegroundColor Yellow
try {
    $response = Invoke-RestMethod -Uri "$baseUrl/api/health" -Method Get -ErrorAction Stop
    Write-Host "   ✓ Health check OK" -ForegroundColor Green
    Write-Host "   Status: $($response.status)" -ForegroundColor Gray
} catch {
    Write-Host "   ✗ Health check failed: $($_.Exception.Message)" -ForegroundColor Red
}

# Test 2: API Info
Write-Host ""
Write-Host "2. Testing API info endpoint..." -ForegroundColor Yellow
try {
    $response = Invoke-RestMethod -Uri "$baseUrl/api/v1" -Method Get -ErrorAction Stop
    Write-Host "   ✓ API info OK" -ForegroundColor Green
    Write-Host "   Name: $($response.name)" -ForegroundColor Gray
    Write-Host "   Version: $($response.version)" -ForegroundColor Gray
    
    if ($response.litellm) {
        Write-Host "   LiteLLM Base URL: $($response.litellm.base_url)" -ForegroundColor Gray
        Write-Host "   LiteLLM Master Key Set: $($response.litellm.master_key_set)" -ForegroundColor Gray
        
        if ($response.litellm.proxy) {
            Write-Host "   LiteLLM Proxy Reachable: $($response.litellm.proxy.reachable)" -ForegroundColor $(if ($response.litellm.proxy.reachable) { "Green" } else { "Red" })
            if ($response.litellm.proxy.error) {
                Write-Host "   Proxy Error: $($response.litellm.proxy.error)" -ForegroundColor Red
            }
            if ($response.litellm.proxy.available_aliases) {
                Write-Host "   Available Aliases: $($response.litellm.proxy.available_aliases -join ', ')" -ForegroundColor Gray
            }
            if ($response.litellm.proxy.missing_aliases) {
                Write-Host "   Missing Aliases: $($response.litellm.proxy.missing_aliases -join ', ')" -ForegroundColor Yellow
            }
        }
    }
} catch {
    Write-Host "   ✗ API info failed: $($_.Exception.Message)" -ForegroundColor Red
}

# Test 3: List Models (with API Key)
Write-Host ""
Write-Host "3. Testing models endpoint (with API key)..." -ForegroundColor Yellow
try {
    $headers = @{
        "Authorization" = "Bearer $apiKey"
        "Content-Type" = "application/json"
    }
    $response = Invoke-RestMethod -Uri "$baseUrl/api/v1/models" -Method Get -Headers $headers -ErrorAction Stop
    Write-Host "   ✓ Models endpoint OK" -ForegroundColor Green
    Write-Host "   Total models: $($response.data.Count)" -ForegroundColor Gray
    foreach ($model in $response.data) {
        Write-Host "     - $($model.id)" -ForegroundColor Gray
    }
} catch {
    Write-Host "   ✗ Models endpoint failed: $($_.Exception.Message)" -ForegroundColor Red
    if ($_.Exception.Response) {
        $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
        $responseBody = $reader.ReadToEnd()
        Write-Host "   Response: $responseBody" -ForegroundColor Red
    }
}

# Test 4: Chat Completion (non-streaming)
Write-Host ""
Write-Host "4. Testing chat completion (non-streaming)..." -ForegroundColor Yellow
try {
    $headers = @{
        "Authorization" = "Bearer $apiKey"
        "Content-Type" = "application/json"
    }
    $body = @{
        model = "cf-fast"
        messages = @(
            @{
                role = "user"
                content = "Say hello in one word"
            }
        )
        max_tokens = 10
        stream = $false
    } | ConvertTo-Json -Depth 10
    
    $response = Invoke-RestMethod -Uri "$baseUrl/api/v1/chat/completions" -Method Post -Headers $headers -Body $body -ErrorAction Stop
    Write-Host "   ✓ Chat completion OK" -ForegroundColor Green
    $content = $response.choices[0].message.content
    Write-Host "   Response: $content" -ForegroundColor Gray
    if ($response.usage) {
        Write-Host "   Usage: $($response.usage.total_tokens) tokens" -ForegroundColor Gray
    }
} catch {
    Write-Host "   ✗ Chat completion failed: $($_.Exception.Message)" -ForegroundColor Red
    if ($_.Exception.Response) {
        $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
        $responseBody = $reader.ReadToEnd()
        Write-Host "   Response: $responseBody" -ForegroundColor Red
    }
}

Write-Host ""
Write-Host "=== Test Complete ===" -ForegroundColor Cyan

