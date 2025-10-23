# print_handler.ps1
# 这是一个处理打印相关操作的PowerShell脚本
# 接收 -Action (GetPrinters, GetPrintQueue, PrintFile)
# 接收 -FilePath, -PrinterName, -LogFile 等参数

param (
    [Parameter(Mandatory=$true)]
    [string]$Action,

    [string]$FilePath,
    [string]$PrinterName,
    [string]$LogFile = "D:\wwwroot\192.168.1.114\print\powershell_log.log" # 默认日志文件路径，与 config.php 保持一致
)

# 强制PowerShell的输出编码为UTF8，以便PHP正确解析JSON
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

# --- 日志函数 (新增) ---
function Write-PsLog {
    param (
        [string]$Message,
        [string]$Level = "INFO" # INFO, WARNING, ERROR
    )
    $Timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $LogEntry = "[$Timestamp] [PowerShell] [$Level] $Message"
    try {
        Add-Content -Path $LogFile -Value $LogEntry -ErrorAction Stop
    } catch {
        # 如果日志写入失败，则输出到控制台，以便 PHP 捕获
        Write-Host "ERROR: Failed to write to log file '$LogFile'. Details: $($_.Exception.Message)"
        Write-Host $LogEntry
    }
}

Write-PsLog "PowerShell script started. Action: $Action, Printer: $PrinterName, File: $FilePath"

# --- Ghostscript 配置 ---
# 请根据您的实际安装路径修改此变量
$GhostscriptPath = "C:\Program Files\gs\gs10.06.0\bin\gswin64c.exe" # 或 gswin32c.exe

function Get-PrintersList {
    try {
        Write-PsLog "Executing Get-PrintersList action."
        $printers = Get-Printer -ErrorAction Stop | Select-Object -ExpandProperty Name
        $printersJson = $printers | ConvertTo-Json -Compress
        Write-PsLog "Printers list retrieved. Outputting JSON."
        Write-Host $printersJson # 输出到标准输出
    } catch {
        Write-PsLog "Error in Get-PrintersList: $($_.Exception.Message)" "ERROR"
        @{ "error" = "获取打印机列表失败"; "details" = "$($_.Exception.Message)"; "script_stack_trace" = "$($_.ScriptStackTrace)" } | ConvertTo-Json -Compress | Write-Host
    }
}

function Get-PrintQueueStatus {
    param (
        [Parameter(Mandatory=$true)]
        [string]$PrinterName
    )
    try {
        Write-PsLog "Executing Get-PrintQueueStatus action for printer: $PrinterName"
        # 检查打印机是否存在
        $printer = Get-Printer -Name $PrinterName -ErrorAction SilentlyContinue
        if (-not $printer) {
            throw "打印机 '$PrinterName' 不存在或不可用。请检查打印机名称和状态。"
        }
        Write-PsLog "Printer '$PrinterName' found. Attempting to get print jobs."
        
        # 获取打印队列作业
        $jobs = Get-PrintJob -PrinterName $PrinterName -ErrorAction Stop
        
        # 如果队列为空，输出空数组的 JSON
        if ($jobs.Count -eq 0) {
            Write-PsLog "Print queue for '$PrinterName' is empty. Outputting empty JSON array."
            @() | ConvertTo-Json -Compress | Write-Host
        } else {
            # 否则，输出作业列表的 JSON
            Write-PsLog "Print queue for '$PrinterName' found $($jobs.Count) jobs. Outputting JSON."
            $jobs | Select-Object DocumentName, Status | ConvertTo-Json -Compress | Write-Host
        }
    } catch {
        Write-PsLog "Error in Get-PrintQueueStatus for printer '$PrinterName': $($_.Exception.Message)" "ERROR"
        @{ "error" = "获取打印队列失败"; "details" = "$($_.Exception.Message)"; "script_stack_trace" = "$($_.ScriptStackTrace)" } | ConvertTo-Json -Compress | Write-Host
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
        Write-PsLog "Executing Print-File action. File: $FilePath, Printer: $PrinterName"
        if (-not (Test-Path $FilePath)) {
            throw "文件不存在: $FilePath"
        }
        Write-PsLog "File '$FilePath' found."

        # 检查目标打印机是否存在
        $targetPrinter = Get-Printer -Name $PrinterName -ErrorAction SilentlyContinue
        if (-not $targetPrinter) {
            throw "打印机 '$PrinterName' 不存在或不可用。"
        }
        Write-PsLog "Printer '$PrinterName' found."

        # 检查 Ghostscript 是否安装
        if (-not (Test-Path $GhostscriptPath)) {
            throw "Ghostscript 可执行文件未找到: $GhostscriptPath。请检查安装路径。"
        }
        Write-PsLog "Ghostscript executable found at: $GhostscriptPath"

        $fileExtension = [System.IO.Path]::GetExtension($FilePath).ToLower()

        if ($fileExtension -eq ".pdf") {
            # 使用 Ghostscript 命令行打印 PDF
            # -dPrinted: 标记为已打印
            # -dBATCH -dNOPAUSE: 批处理模式，不暂停
            # -dSAFER: 安全模式
            # -sDEVICE=mswinpr2: 输出到 Windows 打印机
            # -sOutputFile="%printer%PrinterName": 指定输出到哪个打印机
            # -f: 输入文件
            $GhostscriptArgs = "-dPrinted -dBATCH -dNOPAUSE -dSAFER -sDEVICE=mswinpr2 -sOutputFile=`"%printer%$PrinterName`" `"$FilePath`""

            Write-PsLog "Attempting to print PDF file '$FilePath' to '$PrinterName' using Ghostscript. Command: `"$GhostscriptPath`" $GhostscriptArgs"
            
            # 记录 Start-Process 开始时间
            $start_time = Get-Date
            $process = Start-Process -FilePath $GhostscriptPath -ArgumentList $GhostscriptArgs -Wait -PassThru -ErrorAction Stop -NoNewWindow
            $end_time = Get-Date
            $duration = ($end_time - $start_time).TotalSeconds
            Write-PsLog "Ghostscript process finished. Duration: $($duration)s. Exit code: $($process.ExitCode)"
            
            if ($process.ExitCode -ne 0) {
                throw "Ghostscript 打印失败，退出码: $($process.ExitCode)。请检查 Ghostscript 日志或尝试手动打印。"
            }
        } else {
            throw "文件类型 '$fileExtension' 不支持直接打印。请上传 PDF 文件。"
        }

        Write-PsLog "File '$FilePath' successfully sent to printer '$PrinterName'."
        @{ "success" = "文件 '$FilePath' 已发送到打印机 '$PrinterName'" } | ConvertTo-Json -Compress | Write-Host
    } catch {
        Write-PsLog "Error in Print-File for file '$FilePath' to printer '$PrinterName': $($_.Exception.Message)" "ERROR"
        @{ "error" = "打印文件失败"; "details" = "$($_.Exception.Message)"; "script_stack_trace" = "$($_.ScriptStackTrace)" } | ConvertTo-Json -Compress | Write-Host
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
        Print-File -FilePath $FilePath -PrinterName $PrinterName -LogFile $LogFile # 确保 LogFile 参数传递给 Print-File
    }
    default {
        Write-PsLog "Invalid Action parameter: $Action" "ERROR"
        @{ "error" = "无效的Action参数"; "details" = "$Action" } | ConvertTo-Json -Compress | Write-Host
    }
}