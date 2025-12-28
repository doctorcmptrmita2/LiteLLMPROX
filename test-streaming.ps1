# Streaming Request Test Script
# Usage: .\test-streaming.ps1 -ApiKey "cf_your_key_here"

param(
    [Parameter(Mandatory=$false)]
    [string]$ApiKey = "cf_luwUZsPFM7UkQ3LUNj3TSz8WbaYNQ9V7FxWqFfc1"
)

$uri = "https://codexflow.dev/api/v1/chat/completions"
$headers = @{
    "Content-Type" = "application/json"
    "Authorization" = "Bearer $ApiKey"
}
$body = @{
    model = "cf-fast"
    messages = @(
        @{
            role = "user"
            content = "Merhaba! Kısa bir test mesajı."
        }
    )
    stream = $true
} | ConvertTo-Json -Depth 10

Write-Host "Streaming request gönderiliyor..." -ForegroundColor Yellow
Write-Host "URI: $uri" -ForegroundColor Gray
Write-Host "Model: cf-fast" -ForegroundColor Gray
Write-Host "Stream: true" -ForegroundColor Gray
Write-Host "`n--- Response Stream ---`n" -ForegroundColor Cyan

try {
    $response = Invoke-WebRequest -Uri $uri -Method POST -Headers $headers -Body $body -UseBasicParsing
    
    # Parse SSE stream
    $lines = $response.Content -split "`n"
    $chunkCount = 0
    
    foreach ($line in $lines) {
        $line = $line.Trim()
        
        if ($line -match '^data:\s*(.+)$') {
            $data = $matches[1]
            
            if ($data -eq '[DONE]') {
                Write-Host "`n[DONE] - Stream tamamlandı" -ForegroundColor Green
                break
            }
            
            try {
                $json = $data | ConvertFrom-Json
                
                # Check for errors
                if ($json.error) {
                    Write-Host "`n[ERROR] " -ForegroundColor Red -NoNewline
                    Write-Host ($json.error | ConvertTo-Json -Depth 5)
                    break
                }
                
                # Display chunk
                if ($json.choices -and $json.choices[0].delta.content) {
                    Write-Host $json.choices[0].delta.content -NoNewline -ForegroundColor White
                    $chunkCount++
                }
                
            } catch {
                # Skip invalid JSON chunks
                if ($data -ne '') {
                    Write-Host "`n[SKIP] Invalid JSON: $data" -ForegroundColor Yellow
                }
            }
        }
    }
    
    Write-Host "`n`n--- Stream Özeti ---" -ForegroundColor Cyan
    Write-Host "Toplam chunk: $chunkCount" -ForegroundColor Gray
    Write-Host "Status Code: $($response.StatusCode)" -ForegroundColor Gray
    
} catch {
    Write-Host "`n[ERROR] Request başarısız!" -ForegroundColor Red
    Write-Host "Status: $($_.Exception.Response.StatusCode.value__)" -ForegroundColor Red
    
    if ($_.Exception.Response) {
        $streamReader = [System.IO.StreamReader]::new($_.Exception.Response.GetResponseStream())
        $errorBody = $streamReader.ReadToEnd()
        Write-Host "Error Body:" -ForegroundColor Red
        Write-Host $errorBody -ForegroundColor Yellow
    } else {
        Write-Host "Exception: $($_.Exception.Message)" -ForegroundColor Red
    }
}

