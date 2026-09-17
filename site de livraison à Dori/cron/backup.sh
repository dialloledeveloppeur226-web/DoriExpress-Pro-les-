#!/bin/bash
# =============================================
# CRON - SAUVEGARDE AUTOMATIQUE - DoriExpress-Pro
# =============================================
# Fichier : cron/backup.sh
# Rôle : Sauvegarde de la base de données (version shell)
# =============================================

# Configuration
BACKUP_DIR="/var/www/doriexpress/storage/backups"
DB_NAME="dori_express"
DB_USER="root"
DB_PASS=""
DATE=$(date +"%Y-%m-%d_%H-%M-%S")
BACKUP_FILE="$BACKUP_DIR/backup_$DATE.sql"
MAX_BACKUPS=30

# Créer le dossier si nécessaire
mkdir -p $BACKUP_DIR

# Sauvegarder la base de données
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME > $BACKUP_FILE

# Compresser
gzip $BACKUP_FILE

# Supprimer les anciennes sauvegardes
cd $BACKUP_DIR
ls -t backup_*.sql.gz | tail -n +$((MAX_BACKUPS + 1)) | xargs -r rm

# Journaliser
echo "$DATE - Sauvegarde créée : $(basename $BACKUP_FILE.gz)" >> $BACKUP_DIR/backup.log

# =============================================
# FIN DU FICHIER CRON/BACKUP.SH
# =============================================