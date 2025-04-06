echo off

echo.
set user_default=Atualizacao
set /p user_text=Texto do commit (padrao %user_default%): 
if "%user_text%"=="" set resposta=%user_default%

time /t
echo.
git add .
git commit -m "%user_text%"
git push origin prod
