PREFIX=/data/www/default/

cd ${PREFIX}
INTERVAL=1

nohup /usr/local/php/bin /data/www/default/public/inidex.php /script/Checkparttime/parttimeFinish  >> ${PREFIXLOG}parttimeFinish.log 2>&1 & echo $! > ./parttimeFinish.pid


while [ 1 ]; do
    if [ ! -d /proc/`cat ./parttimeFinish.pid` ]; then
        nohup /usr/local/php/bin /data/www/default/public/inidex.php /script/Checkparttime/parttimeFinish  >> ${PREFIXLOG}parttimeFinish.log 2>&1 & echo $! > ./parttimeFinish.pid
        echo 'NEW_PID:'`cat ./parttimeFinish.pid && date '+%Y-%m-%d %H:%M:%S'`
    fi
    sleep ${INTERVAL}
done

