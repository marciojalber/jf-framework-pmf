echo off

git init
git remote add origin https://github.com/marciojalber/jf-framework-pmf.git
git fetch
git checkout prod
git pull origin prod
