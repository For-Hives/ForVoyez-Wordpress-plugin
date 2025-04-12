#!/bin/bash

# Script pour ajouter une nouvelle langue au plugin Auto Alt Text for Images
# Usage: bash bin/add-language.sh [language_code] (e.g. bash bin/add-language.sh es_ES)

# Vérifier si le code de langue est fourni
if [ -z "$1" ]; then
    echo "ERROR: No language code provided!"
    echo "Usage: bash bin/add-language.sh [language_code]"
    echo "Example: bash bin/add-language.sh es_ES"
    exit 1
fi

LANG_CODE=$1
LANG_CODE_SHORT=${LANG_CODE%_*}
PLUGIN_NAME="auto-alt-text-for-images"
LANG_DIR="languages"

echo "Creating translation files for language: $LANG_CODE"

# Vérifier si le répertoire languages existe
if [ ! -d "$LANG_DIR" ]; then
    echo "Creating languages directory..."
    mkdir -p "$LANG_DIR"
fi

# Créer le fichier PO du plugin
if [ ! -f "$LANG_DIR/$PLUGIN_NAME-$LANG_CODE.po" ]; then
    echo "Creating plugin PO file: $LANG_DIR/$PLUGIN_NAME-$LANG_CODE.po"
    cp "$LANG_DIR/$PLUGIN_NAME.pot" "$LANG_DIR/$PLUGIN_NAME-$LANG_CODE.po"
    
    # Mettre à jour les en-têtes
    sed -i -e "s/^\"Language: .*\$/\"Language: $LANG_CODE\\n\"/" "$LANG_DIR/$PLUGIN_NAME-$LANG_CODE.po"
    sed -i -e "s/^\"PO-Revision-Date: .*\$/\"PO-Revision-Date: $(date +%Y-%m-%d\ %H:%M%z)\\n\"/" "$LANG_DIR/$PLUGIN_NAME-$LANG_CODE.po"
    
    echo "Plugin PO file created successfully!"
else
    echo "Plugin PO file already exists: $LANG_DIR/$PLUGIN_NAME-$LANG_CODE.po"
fi

# Créer le fichier PO du readme
if [ ! -f "$LANG_DIR/$PLUGIN_NAME-readme-$LANG_CODE_SHORT.po" ]; then
    echo "Creating readme PO file: $LANG_DIR/$PLUGIN_NAME-readme-$LANG_CODE_SHORT.po"
    
    # Utiliser un fichier existant comme modèle
    if [ -f "$LANG_DIR/$PLUGIN_NAME-readme-fr.po" ]; then
        cp "$LANG_DIR/$PLUGIN_NAME-readme-fr.po" "$LANG_DIR/$PLUGIN_NAME-readme-$LANG_CODE_SHORT.po"
        
        # Mettre à jour les en-têtes
        sed -i -e "s/in French (France)/in ${LANG_CODE_SHORT^}/" "$LANG_DIR/$PLUGIN_NAME-readme-$LANG_CODE_SHORT.po"
        sed -i -e "s/^\"Language: .*\$/\"Language: $LANG_CODE_SHORT\\n\"/" "$LANG_DIR/$PLUGIN_NAME-readme-$LANG_CODE_SHORT.po"
        sed -i -e "s/^\"PO-Revision-Date: .*\$/\"PO-Revision-Date: $(date +%Y-%m-%d\ %H:%M%z)\\n\"/" "$LANG_DIR/$PLUGIN_NAME-readme-$LANG_CODE_SHORT.po"
        
        # Supprimer toutes les traductions existantes
        sed -i -e 's/^msgstr ".*"$/msgstr ""/' "$LANG_DIR/$PLUGIN_NAME-readme-$LANG_CODE_SHORT.po"
        
        echo "Readme PO file created successfully!"
    else
        echo "Warning: Could not find template file $LANG_DIR/$PLUGIN_NAME-readme-fr.po"
        echo "Please create $LANG_DIR/$PLUGIN_NAME-readme-$LANG_CODE_SHORT.po manually."
    fi
else
    echo "Readme PO file already exists: $LANG_DIR/$PLUGIN_NAME-readme-$LANG_CODE_SHORT.po"
fi

# Créer le fichier readme.txt
if [ ! -f "$LANG_DIR/readme-$LANG_CODE.txt" ]; then
    echo "Creating readme file: $LANG_DIR/readme-$LANG_CODE.txt"
    
    # Utiliser un fichier existant comme modèle
    if [ -f "$LANG_DIR/readme-fr_FR.txt" ]; then
        cp "$LANG_DIR/readme-fr_FR.txt" "$LANG_DIR/readme-$LANG_CODE.txt"
        
        # Supprimer le contenu traduit et garder uniquement la structure
        TEMP_FILE=$(mktemp)
        awk '/^=== Auto Alt Text for Images ===/{p=1} p' "$LANG_DIR/readme-$LANG_CODE.txt" > "$TEMP_FILE"
        mv "$TEMP_FILE" "$LANG_DIR/readme-$LANG_CODE.txt"
        
        # Remplacer le contenu traduit par le contenu en anglais
        if [ -f "$LANG_DIR/readme-de_DE.txt" ]; then
            # Si le fichier allemand existe, nous l'utilisons car il contient déjà le contenu en anglais
            CONTENT=$(awk '/^=== Auto Alt Text for Images ===/{p=1} p' "$LANG_DIR/readme-de_DE.txt")
            echo "$CONTENT" > "$LANG_DIR/readme-$LANG_CODE.txt"
        fi
        
        echo "Readme file created successfully!"
    else
        echo "Warning: Could not find template file $LANG_DIR/readme-fr_FR.txt"
        echo "Please create $LANG_DIR/readme-$LANG_CODE.txt manually."
    fi
else
    echo "Readme file already exists: $LANG_DIR/readme-$LANG_CODE.txt"
fi

echo "Translation files for $LANG_CODE have been successfully created!"
echo "Please translate the files in $LANG_DIR/"
echo "After translation, compile the PO files to MO files with 'msgfmt' or a PO editor like Poedit." 