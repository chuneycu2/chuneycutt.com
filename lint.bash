errors_string="";
while IFS= read -r -d '' -u 9; do
    file_results=`php -l $REPLY -f`;
    if [[ $? -ne 0 ]] #error code is not equal to 0
    then
        errors_string="${errors_string} ${file_results} \n";
    fi
    echo $file_results;
done 9< <( find ./wp-content -name '*.php'  -type f -exec printf '%s\0' {} + );

status=0;
printf "\n\n";
if [[ -z $errors_string ]] #length is not zero
then
    echo "===== SUCCESS =====";
else
    echo "===== FAILURE =====";
    status=1;
fi

echo -e "${errors_string}";
exit $status;