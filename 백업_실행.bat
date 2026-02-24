@echo off
chcp 65001 >nul
setlocal
set "SRC=%~dp0"
set "PARENT=%~dp0.."
set "BACKUP=%PARENT%\백업_쿠폰적용버전_%date:~0,4%%date:~5,2%%date:~8,2%_%time:~0,2%%time:~3,2%%time:~6,2%"
set "BACKUP=%BACKUP: =0%"
echo 백업 대상: %SRC%
echo 백업 위치: %BACKUP%
if not exist "%BACKUP%" mkdir "%BACKUP%"
xcopy "%SRC%*" "%BACKUP%\" /E /I /H /Y
echo.
echo 백업이 완료되었습니다: %BACKUP%
pause
