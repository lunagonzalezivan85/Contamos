<#
  Credenciales del server — copiar este archivo como deploy.config.ps1
  (gitignored, NUNCA se sube al repo) y completar con los valores reales.

  Lo usan: deploy.ps1, deploy-watch.ps1 y sync-bd.ps1.
  Las claves van entre comillas SIMPLES para que $ no se interpole en PS.
#>
$FtpHost   = 'ftp://HOST_FTP'              # con esquema ftp:// — sin él curl usa HTTP y no sube nada
$FtpUser   = 'USUARIO_FTP'
$FtpPass   = 'CLAVE_FTP'
$RemoteDir = '/'                           # raíz de la cuenta FTP = raíz del proyecto ('' o '/')

$DbHost    = 'HOST_MYSQL'
$DbUser    = 'USUARIO_MYSQL'
$DbPass    = 'CLAVE_MYSQL'
$DbName    = 'NOMBRE_BD'
