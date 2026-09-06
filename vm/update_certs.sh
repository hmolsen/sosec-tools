 cd /home/kali/Vulnerads && wget https://cqrity.de/vm/certs.tar && tar -xvf certs.tar && rm -rf certs.tar && openssl pkcs12 -export -in vulnerads.de.fullchain.pem -inkey vulnerads.de.private_key.pem -out /home/kali/Vulnerads/vulnerads/src/main/resources/vulnerads.de.p12 -name sosec -passout pass:sosec && openssl pkcs12 -export -in attacat.de.fullchain.pem -inkey attacat.de.private_key.pem -out /home/kali/Vulnerads/attacat-8666/conf/attacat.de.p12 -name sosec -passout pass:sosec

# 1.) Generate new certificates: https://cqrity.de/vm/certgen.php
#     This creates the certs.tar ball with newly generated certificates.
# 2.) From anywhere on the command shell execute this
# curl -s https://cqrity.de/vm/update_certs.sh | bash -s