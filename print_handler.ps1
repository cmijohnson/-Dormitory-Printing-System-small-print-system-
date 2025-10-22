# print_handler.ps1
# 这是一个处理打印相关操作的PowerShell脚本
# 接收 -Action (GetPrinters, GetPrintQueue, PrintFile)
# 接收 -FilePath, -PrinterName 等参数

param (
    [Parameter(Mandatory=$true)]
    [string]$Action,

    [string]$FilePath,
    [string]$PrinterName
)

# 强制PowerShell的输出编码为UTF8，以便PHP正确解析JSON
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

function Get-PrintersList {
    try {
        Get-Printer | Select-Object -ExpandProperty Name | ConvertTo-Json -Compress
    } catch {
        @{ "error" = "获取打印机列表失败: $($_.Exception.Message)" } | ConvertTo-Json -Compress
    }
}

function Get-PrintQueueStatus {
    param (
        [Parameter(Mandatory=$true)]
        [string]$PrinterName
    )
    try {
        # 检查打印机是否存在
        if (-not (Get-Printer -Name $PrinterName -ErrorAction SilentlyContinue)) {
            throw "打印机 '$PrinterName' 不存在。"
        }
        Get-PrintJob -PrinterName $PrinterName | Select-Object DocumentName, Status | ConvertTo-Json -Compress
    } catch {
        @{ "error" = "获取打印队列失败: $($_.Exception.Message)" } | ConvertTo-Json -Compress
    }
}

function Print-File {
    param (
        [Parameter(Mandatory=$true)]
        [string]$FilePath,
        [Parameter(Mandatory=$true)]
        [string]$PrinterName
    )
    try {
        if (-not (Test-Path $FilePath)) {
            throw "文件不存在: $FilePath"
        }
        # 检查打印机是否存在
        if (-not (Get-Printer -Name $PrinterName -ErrorAction SilentlyContinue)) {
            throw "打印机 '$PrinterName' 不存在。"
        }

        # 使用Start-Process -Verb Print 来打印文件
        # 这会调用文件关联的默认程序来打印
        # 注意：服务器上需要安装对应的应用程序（如Adobe Reader for PDF, MS Office for DOCX/XLSX）
        # 并且运行IIS/Nginx的用户需要有权限启动这些应用程序并访问打印机
        Start-Process -FilePath $FilePath -Verb Print -Printer $PrinterName -PassThru | Wait-Process -Timeout 60 # 等待打印进程完成，最多60秒

        @{ "success" = "文件 '$FilePath' 已发送到打印机 '$PrinterName'" } | ConvertTo-Json -Compress
    } catch {
        @{ "error" = "打印文件失败: $($_.Exception.Message)" } | ConvertTo-Json -Compress
    }
}

switch ($Action) {
    "GetPrinters" {
        Get-PrintersList
    }
    "GetPrintQueue" {
        Get-PrintQueueStatus -PrinterName $PrinterName
    }
    "PrintFile" {
        Print-File -FilePath $FilePath -PrinterName $PrinterName
    }
    default {
        @{ "error" = "无效的Action参数: $Action" } | ConvertTo-Json -Compress
    }
}