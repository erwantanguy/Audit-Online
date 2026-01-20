/**
 * Export PDF Professionnel pour GEO Audit Tool
 * Version 2.0 - PDF actionnable pour professionnels
 * 
 * Fonctionnalités :
 * - Checklist d'actions avec cases à cocher
 * - Exemples de code JSON-LD à copier-coller
 * - Comparaison avant/après avec objectifs
 * - Liste des images sans alt avec URLs
 * - Recommandations par priorité
 */

function exportPDF() {
    if (!auditData) {
        alert('Aucune donnée à exporter');
        return;
    }

    try {
        if (typeof window.jspdf === 'undefined') {
            alert('Erreur: Bibliothèque PDF non chargée');
            return;
        }

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('p', 'mm', 'a4');

        const pageWidth = 210;
        const pageHeight = 297;
        const margin = 15;
        const contentWidth = pageWidth - (margin * 2);
        let y = 15;
        let pageNum = 1;

        // ==============================================
        // HELPERS
        // ==============================================

        function checkPageBreak(needed = 20) {
            if (y + needed > pageHeight - 20) {
                doc.addPage();
                pageNum++;
                y = 20;
                addFooter();
                return true;
            }
            return false;
        }

        function addFooter() {
            doc.setFontSize(8);
            doc.setTextColor(150, 150, 150);
            doc.text(`Page ${pageNum}`, pageWidth - margin, pageHeight - 10);
            doc.text('Rapport GEO Audit - ticoet.fr', margin, pageHeight - 10);
            doc.setTextColor(0, 0, 0);
        }

        function addText(text, x, yPos, maxWidth, lineHeight = 5) {
            const lines = doc.splitTextToSize(String(text || ''), maxWidth);
            lines.forEach(line => {
                if (yPos + lineHeight > pageHeight - 20) {
                    doc.addPage();
                    pageNum++;
                    yPos = 20;
                    addFooter();
                }
                doc.text(line, x, yPos);
                yPos += lineHeight;
            });
            return yPos;
        }

        function addSectionTitle(title, icon = '') {
            checkPageBreak(20);
            y += 5;
            doc.setFillColor(102, 126, 234);
            doc.rect(margin, y - 5, contentWidth, 10, 'F');
            doc.setFontSize(14);
            doc.setFont('helvetica', 'bold');
            doc.setTextColor(255, 255, 255);
            doc.text(icon + ' ' + title, margin + 3, y + 1);
            doc.setTextColor(0, 0, 0);
            y += 12;
        }

        function addSubTitle(title) {
            checkPageBreak(15);
            doc.setFontSize(11);
            doc.setFont('helvetica', 'bold');
            doc.setTextColor(102, 126, 234);
            doc.text(title, margin, y);
            doc.setTextColor(0, 0, 0);
            y += 6;
        }

        function createBox(x, yPos, width, height, color) {
            doc.setFillColor(...color);
            doc.roundedRect(x, yPos, width, height, 2, 2, 'F');
        }

        function addCheckbox(text, checked = false, priority = 'medium') {
            checkPageBreak(8);
            
            // Case à cocher
            doc.setDrawColor(100, 100, 100);
            doc.setLineWidth(0.3);
            doc.rect(margin, y - 3, 4, 4);
            
            if (checked) {
                doc.setDrawColor(40, 163, 42);
                doc.line(margin + 0.5, y - 1, margin + 1.5, y);
                doc.line(margin + 1.5, y, margin + 3.5, y - 2.5);
            }
            
            // Priorité
            let priorityColor, priorityText;
            if (priority === 'high') {
                priorityColor = [220, 53, 69];
                priorityText = 'CRITIQUE';
            } else if (priority === 'medium') {
                priorityColor = [255, 193, 7];
                priorityText = 'IMPORTANT';
            } else {
                priorityColor = [40, 167, 69];
                priorityText = 'OPTIONNEL';
            }
            
            doc.setFillColor(...priorityColor);
            doc.roundedRect(margin + 6, y - 4, 18, 5, 1, 1, 'F');
            doc.setFontSize(6);
            doc.setTextColor(255, 255, 255);
            doc.text(priorityText, margin + 7, y - 0.5);
            
            // Texte
            doc.setFontSize(9);
            doc.setTextColor(0, 0, 0);
            doc.setFont('helvetica', 'normal');
            y = addText(text, margin + 26, y, contentWidth - 30, 4);
            y += 2;
        }

        function addCodeBlock(code, language = 'json') {
            checkPageBreak(40);
            
            const lines = code.split('\n');
            const blockHeight = Math.min(lines.length * 4 + 6, 80);
            
            createBox(margin, y, contentWidth, blockHeight, [45, 45, 45]);
            
            doc.setFontSize(7);
            doc.setFont('courier', 'normal');
            doc.setTextColor(248, 248, 242);
            
            let codeY = y + 4;
            lines.forEach((line, idx) => {
                if (idx < 18 && codeY < y + blockHeight - 4) {
                    doc.text(line.substring(0, 90), margin + 3, codeY);
                    codeY += 4;
                }
            });
            
            if (lines.length > 18) {
                doc.setTextColor(150, 150, 150);
                doc.text('... (code tronqué)', margin + 3, codeY);
            }
            
            doc.setFont('helvetica', 'normal');
            doc.setTextColor(0, 0, 0);
            y += blockHeight + 5;
        }

        // ==============================================
        // PAGE 1: COUVERTURE & RÉSUMÉ
        // ==============================================

        // En-tête violet
        doc.setFillColor(102, 126, 234);
        doc.rect(0, 0, pageWidth, 50, 'F');

        doc.setTextColor(255, 255, 255);
        doc.setFontSize(28);
        doc.setFont('helvetica', 'bold');
        doc.text('RAPPORT D\'AUDIT GEO', pageWidth / 2, 22, { align: 'center' });

        doc.setFontSize(12);
        doc.setFont('helvetica', 'normal');
        doc.text('Guide d\'optimisation pour les moteurs d\'IA', pageWidth / 2, 32, { align: 'center' });

        // URL et date
        doc.setFontSize(9);
        const urlShort = auditData.url.length > 60 ? auditData.url.substring(0, 57) + '...' : auditData.url;
        doc.text(urlShort, pageWidth / 2, 42, { align: 'center' });

        y = 60;
        doc.setTextColor(0, 0, 0);

        // Score principal
        const score = auditData.score;
        const scoreText = Number.isInteger(score) ? score.toString() : score.toFixed(1);
        
        let scoreColor, scoreBg, scoreLabel;
        if (score >= 80) {
            scoreColor = [40, 163, 42];
            scoreBg = [212, 237, 218];
            scoreLabel = 'EXCELLENT';
        } else if (score >= 50) {
            scoreColor = [180, 130, 0];
            scoreBg = [255, 243, 205];
            scoreLabel = 'BON - Améliorations possibles';
        } else {
            scoreColor = [200, 50, 50];
            scoreBg = [248, 215, 218];
            scoreLabel = 'À AMÉLIORER - Actions requises';
        }

        createBox(margin, y, contentWidth, 35, scoreBg);
        
        doc.setFontSize(48);
        doc.setFont('helvetica', 'bold');
        doc.setTextColor(...scoreColor);
        doc.text(scoreText, margin + 25, y + 25);
        
        doc.setFontSize(16);
        doc.text('/100', margin + 55, y + 25);
        
        doc.setFontSize(14);
        doc.setFont('helvetica', 'normal');
        doc.text(scoreLabel, margin + 80, y + 20);
        
        const date = new Date().toLocaleDateString('fr-FR', { 
            year: 'numeric', month: 'long', day: 'numeric'
        });
        doc.setFontSize(9);
        doc.setTextColor(100, 100, 100);
        doc.text('Analyse du ' + date, margin + 80, y + 28);

        y += 45;
        doc.setTextColor(0, 0, 0);

        // Résumé des scores par catégorie
        addSubTitle('Répartition du score');
        
        const breakdown = auditData.breakdown;
        const categories = [
            { name: 'Entités Schema.org', score: breakdown.entities, max: 30 },
            { name: 'Médias optimisés', score: breakdown.media, max: 25 },
            { name: 'Structure contenu', score: breakdown.structure, max: 25 },
            { name: 'Métadonnées', score: breakdown.metadata, max: 20 }
        ];

        categories.forEach(cat => {
            const pct = (cat.score / cat.max) * 100;
            const barWidth = (contentWidth - 50) * (pct / 100);
            
            doc.setFontSize(9);
            doc.setFont('helvetica', 'normal');
            doc.text(cat.name, margin, y + 3);
            
            // Barre de fond
            doc.setFillColor(230, 230, 230);
            doc.rect(margin + 45, y, contentWidth - 50, 5, 'F');
            
            // Barre de progression
            if (pct >= 70) {
                doc.setFillColor(40, 163, 42);
            } else if (pct >= 40) {
                doc.setFillColor(255, 193, 7);
            } else {
                doc.setFillColor(220, 53, 69);
            }
            doc.rect(margin + 45, y, barWidth, 5, 'F');
            
            // Score
            const scoreDisplay = Number.isInteger(cat.score) ? cat.score : cat.score.toFixed(1);
            doc.text(`${scoreDisplay}/${cat.max}`, pageWidth - margin - 15, y + 3);
            
            y += 8;
        });

        y += 5;

        // Objectif de score
        if (score < 80) {
            createBox(margin, y, contentWidth, 20, [232, 244, 253]);
            doc.setFontSize(10);
            doc.setFont('helvetica', 'bold');
            doc.setTextColor(13, 110, 253);
            doc.text('OBJECTIF : Atteindre 80/100 pour une optimisation GEO efficace', margin + 5, y + 8);
            
            const pointsNeeded = Math.ceil(80 - score);
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(9);
            doc.text(`Il vous manque ${pointsNeeded} points. Suivez les actions ci-dessous pour les gagner.`, margin + 5, y + 15);
            doc.setTextColor(0, 0, 0);
            y += 25;
        }

        addFooter();

        // ==============================================
        // PAGE 2: CHECKLIST D'ACTIONS PRIORITAIRES
        // ==============================================

        doc.addPage();
        pageNum++;
        y = 20;
        addFooter();

        addSectionTitle('CHECKLIST D\'ACTIONS', '');

        doc.setFontSize(9);
        doc.setTextColor(100, 100, 100);
        doc.text('Cochez chaque action une fois réalisée. Commencez par les actions CRITIQUES.', margin, y);
        y += 8;
        doc.setTextColor(0, 0, 0);

        // Trier les recommandations par priorité
        const recs = auditData.recommendations || [];
        const highPriority = recs.filter(r => r.priority === 'high');
        const mediumPriority = recs.filter(r => r.priority === 'medium');
        const lowPriority = recs.filter(r => r.priority === 'low');

        // Actions critiques
        if (highPriority.length > 0) {
            addSubTitle('Actions critiques (à faire en premier)');
            highPriority.forEach(rec => {
                addCheckbox(rec.message, false, 'high');
            });
            y += 3;
        }

        // Actions importantes
        if (mediumPriority.length > 0) {
            addSubTitle('Actions importantes');
            mediumPriority.forEach(rec => {
                addCheckbox(rec.message, false, 'medium');
            });
            y += 3;
        }

        // Actions optionnelles
        if (lowPriority.length > 0) {
            addSubTitle('Actions optionnelles');
            lowPriority.forEach(rec => {
                addCheckbox(rec.message, false, 'low');
            });
        }

        // Actions spécifiques basées sur l'analyse
        y += 5;
        addSubTitle('Actions détaillées');

        // Images sans alt
        if (auditData.media.imagesWithoutAlt > 0) {
            addCheckbox(
                `Ajouter l'attribut alt à ${auditData.media.imagesWithoutAlt} image(s) - Voir liste page suivante`,
                false,
                'high'
            );
        }

        // JSON-LD
        if (!auditData.content.hasJSONLD) {
            addCheckbox(
                'Implémenter le balisage JSON-LD Schema.org - Voir exemples de code ci-après',
                false,
                'high'
            );
        }

        // FAQ
        if (auditData.content.faq === 0) {
            addCheckbox(
                'Créer une section FAQ avec au moins 3 questions/réponses',
                false,
                'high'
            );
        }

        // Citations
        if (auditData.content.blockquotes === 0) {
            addCheckbox(
                'Ajouter des citations d\'experts ou de sources fiables',
                false,
                'medium'
            );
        }

        // Vidéos
        if (auditData.media.videos === 0) {
            addCheckbox(
                'Intégrer une vidéo explicative ou tutoriel',
                false,
                'medium'
            );
        }

        // Open Graph
        if (!auditData.metadata.hasOG) {
            addCheckbox(
                'Configurer les balises Open Graph (og:title, og:description, og:image)',
                false,
                'medium'
            );
        }

        // ==============================================
        // PAGE 3: IMAGES SANS ALT
        // ==============================================

        if (auditData.media.imagesWithoutAltDetails && auditData.media.imagesWithoutAltDetails.length > 0) {
            doc.addPage();
            pageNum++;
            y = 20;
            addFooter();

            addSectionTitle('IMAGES SANS ATTRIBUT ALT', '');

            doc.setFontSize(9);
            doc.setTextColor(100, 100, 100);
            doc.text('Ces images nécessitent un attribut alt descriptif pour le SEO et l\'accessibilité.', margin, y);
            y += 8;
            doc.setTextColor(0, 0, 0);

            // En-tête du tableau
            createBox(margin, y - 2, contentWidth, 7, [102, 126, 234]);
            doc.setFontSize(8);
            doc.setFont('helvetica', 'bold');
            doc.setTextColor(255, 255, 255);
            doc.text('N°', margin + 2, y + 2);
            doc.text('URL de l\'image', margin + 12, y + 2);
            doc.text('Action suggérée', margin + 130, y + 2);
            doc.setTextColor(0, 0, 0);
            doc.setFont('helvetica', 'normal');
            y += 8;

            auditData.media.imagesWithoutAltDetails.forEach((img, idx) => {
                checkPageBreak(12);
                
                const bgColor = idx % 2 === 0 ? [250, 250, 250] : [255, 255, 255];
                createBox(margin, y - 3, contentWidth, 10, bgColor);
                
                doc.setFontSize(8);
                doc.text(`${idx + 1}`, margin + 2, y + 2);
                
                // URL tronquée
                let urlDisplay = img.src;
                if (urlDisplay.length > 65) {
                    urlDisplay = urlDisplay.substring(0, 62) + '...';
                }
                doc.text(urlDisplay, margin + 12, y + 2);
                
                // Suggestion
                doc.setFontSize(7);
                doc.setTextColor(100, 100, 100);
                doc.text('Ajouter alt=""', margin + 130, y + 2);
                doc.setTextColor(0, 0, 0);
                
                y += 10;
            });

            // Exemple de correction
            y += 5;
            addSubTitle('Exemple de correction');
            doc.setFontSize(8);
            doc.text('Avant:', margin, y);
            y += 4;
            addCodeBlock('<img src="image.jpg">');
            
            doc.setFontSize(8);
            doc.text('Après:', margin, y);
            y += 4;
            addCodeBlock('<img src="image.jpg" alt="Description précise de l\'image">');
        }

        // ==============================================
        // PAGE 4: EXEMPLES DE CODE JSON-LD
        // ==============================================

        doc.addPage();
        pageNum++;
        y = 20;
        addFooter();

        addSectionTitle('EXEMPLES DE CODE JSON-LD', '');

        doc.setFontSize(9);
        doc.setTextColor(100, 100, 100);
        doc.text('Copiez ces blocs de code dans la section <head> de votre page HTML.', margin, y);
        y += 8;
        doc.setTextColor(0, 0, 0);

        // Organization (si manquant)
        if (auditData.entities.organization === 0) {
            addSubTitle('1. Entité Organization (CRITIQUE)');
            doc.setFontSize(8);
            doc.text('Ajoutez ce code pour identifier votre entreprise :', margin, y);
            y += 5;

            const orgCode = `<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Organization",
  "name": "Nom de votre entreprise",
  "url": "${auditData.url.split('/').slice(0, 3).join('/')}",
  "logo": "https://votre-site.com/logo.png",
  "description": "Description de votre activité",
  "sameAs": [
    "https://www.linkedin.com/company/votre-entreprise",
    "https://twitter.com/votre-compte"
  ]
}
</script>`;
            addCodeBlock(orgCode);
        }

        // FAQ (si manquant)
        if (auditData.content.faq === 0) {
            checkPageBreak(60);
            addSubTitle('2. FAQPage Schema (CRITIQUE)');
            doc.setFontSize(8);
            doc.text('Structurez vos FAQ pour qu\'elles apparaissent dans les résultats IA :', margin, y);
            y += 5;

            const faqCode = `<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "Votre première question ?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "La réponse détaillée à cette question."
      }
    },
    {
      "@type": "Question",
      "name": "Votre deuxième question ?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "La réponse à cette deuxième question."
      }
    }
  ]
}
</script>`;
            addCodeBlock(faqCode);
        }

        // Article (pour les pages article)
        if (auditData.pageType === 'article') {
            checkPageBreak(60);
            addSubTitle('3. Article Schema');
            doc.setFontSize(8);
            doc.text('Pour les articles de blog, ajoutez ce balisage :', margin, y);
            y += 5;

            const articleCode = `<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Article",
  "headline": "${auditData.metadata.title || 'Titre de votre article'}",
  "author": {
    "@type": "Person",
    "name": "Nom de l'auteur"
  },
  "datePublished": "${new Date().toISOString().split('T')[0]}",
  "image": "https://votre-site.com/image-article.jpg",
  "publisher": {
    "@type": "Organization",
    "name": "Nom de votre site"
  }
}
</script>`;
            addCodeBlock(articleCode);
        }

        // ==============================================
        // PAGE 5: COMPARAISON AVANT/APRÈS
        // ==============================================

        doc.addPage();
        pageNum++;
        y = 20;
        addFooter();

        addSectionTitle('OBJECTIFS D\'AMELIORATION', '');

        doc.setFontSize(9);
        doc.text('Voici les gains de points possibles en appliquant les recommandations :', margin, y);
        y += 10;

        // Tableau avant/après
        const improvements = [];
        
        if (auditData.entities.organization === 0) {
            improvements.push({ action: 'Ajouter Organization', points: 10, priority: 'high' });
        }
        if (auditData.content.faq === 0) {
            improvements.push({ action: 'Ajouter FAQ Schema', points: 15, priority: 'high' });
        }
        if (auditData.media.imagesWithoutAlt > 0) {
            const imgPoints = Math.min(10, auditData.media.imagesWithoutAlt * 2);
            improvements.push({ action: `Corriger ${auditData.media.imagesWithoutAlt} images sans alt`, points: imgPoints, priority: 'high' });
        }
        if (!auditData.content.hasJSONLD) {
            improvements.push({ action: 'Ajouter JSON-LD', points: 5, priority: 'high' });
        }
        if (auditData.content.blockquotes === 0) {
            improvements.push({ action: 'Ajouter citations', points: 5, priority: 'medium' });
        }
        if (auditData.media.videos === 0) {
            improvements.push({ action: 'Ajouter vidéo', points: 10, priority: 'medium' });
        }
        if (!auditData.metadata.hasOG) {
            improvements.push({ action: 'Configurer Open Graph', points: 5, priority: 'medium' });
        }

        // En-tête tableau
        createBox(margin, y - 2, contentWidth, 8, [102, 126, 234]);
        doc.setFontSize(9);
        doc.setFont('helvetica', 'bold');
        doc.setTextColor(255, 255, 255);
        doc.text('Action', margin + 5, y + 3);
        doc.text('Priorité', margin + 100, y + 3);
        doc.text('Gain estimé', margin + 140, y + 3);
        doc.setTextColor(0, 0, 0);
        doc.setFont('helvetica', 'normal');
        y += 10;

        let totalGain = 0;
        improvements.forEach((imp, idx) => {
            checkPageBreak(10);
            
            const bgColor = idx % 2 === 0 ? [250, 250, 250] : [255, 255, 255];
            createBox(margin, y - 3, contentWidth, 8, bgColor);
            
            doc.setFontSize(9);
            doc.text(imp.action, margin + 5, y + 2);
            
            // Badge priorité
            let pColor;
            if (imp.priority === 'high') pColor = [220, 53, 69];
            else if (imp.priority === 'medium') pColor = [255, 193, 7];
            else pColor = [40, 167, 69];
            
            doc.setFillColor(...pColor);
            doc.roundedRect(margin + 100, y - 2, 25, 5, 1, 1, 'F');
            doc.setFontSize(7);
            doc.setTextColor(255, 255, 255);
            doc.text(imp.priority === 'high' ? 'CRITIQUE' : imp.priority === 'medium' ? 'IMPORTANT' : 'OPTION', margin + 102, y + 1);
            doc.setTextColor(0, 0, 0);
            
            doc.setFontSize(9);
            doc.setTextColor(40, 163, 42);
            doc.text(`+${imp.points} pts`, margin + 145, y + 2);
            doc.setTextColor(0, 0, 0);
            
            totalGain += imp.points;
            y += 10;
        });

        // Total et projection
        y += 5;
        createBox(margin, y, contentWidth, 25, [212, 237, 218]);
        
        doc.setFontSize(11);
        doc.setFont('helvetica', 'bold');
        doc.text('Score actuel :', margin + 10, y + 8);
        doc.text(`${scoreText}/100`, margin + 60, y + 8);
        
        doc.text('Gain potentiel :', margin + 10, y + 16);
        doc.setTextColor(40, 163, 42);
        doc.text(`+${Math.min(totalGain, 100 - score)} points`, margin + 60, y + 16);
        
        const projectedScore = Math.min(100, score + totalGain);
        doc.setTextColor(0, 0, 0);
        doc.text('Score projeté :', margin + 100, y + 8);
        doc.setFontSize(16);
        doc.setTextColor(40, 163, 42);
        doc.text(`${projectedScore.toFixed(0)}/100`, margin + 145, y + 12);
        
        doc.setTextColor(0, 0, 0);
        doc.setFont('helvetica', 'normal');
        y += 35;

        // ==============================================
        // PAGE 6: ENTITÉS ET MÉDIAS DÉTECTÉS
        // ==============================================

        doc.addPage();
        pageNum++;
        y = 20;
        addFooter();

        addSectionTitle('ANALYSE DETAILLEE', '');

        // Entités détectées
        addSubTitle('Entités Schema.org détectées');
        
        if (auditData.entities.details && auditData.entities.details.length > 0) {
            auditData.entities.details.forEach((entity, idx) => {
                checkPageBreak(15);
                
                doc.setFontSize(9);
                doc.setFont('helvetica', 'bold');
                doc.setTextColor(102, 126, 234);
                doc.text(`${entity.type}:`, margin, y);
                doc.setTextColor(0, 0, 0);
                doc.setFont('helvetica', 'normal');
                doc.text(entity.name || 'Sans nom', margin + 30, y);
                
                if (entity.hasJSONLD) {
                    doc.setFillColor(40, 163, 42);
                    doc.roundedRect(margin + 120, y - 3, 20, 5, 1, 1, 'F');
                    doc.setFontSize(6);
                    doc.setTextColor(255, 255, 255);
                    doc.text('JSON-LD', margin + 122, y);
                    doc.setTextColor(0, 0, 0);
                }
                
                y += 7;
            });
        } else {
            doc.setFontSize(9);
            doc.setTextColor(200, 50, 50);
            doc.text('Aucune entité Schema.org détectée - Action critique requise', margin, y);
            doc.setTextColor(0, 0, 0);
            y += 7;
        }

        y += 5;

        // FAQ détectées
        addSubTitle('FAQ détectées');
        
        if (auditData.content.faqDetails && auditData.content.faqDetails.length > 0) {
            auditData.content.faqDetails.slice(0, 5).forEach((faq, idx) => {
                checkPageBreak(12);
                
                doc.setFontSize(9);
                doc.setFont('helvetica', 'bold');
                doc.text(`Q${idx + 1}:`, margin, y);
                doc.setFont('helvetica', 'normal');
                
                const question = faq.question.length > 70 ? faq.question.substring(0, 67) + '...' : faq.question;
                doc.text(question, margin + 10, y);
                y += 6;
            });
            
            if (auditData.content.hasFAQSchema) {
                doc.setFillColor(40, 163, 42);
                doc.roundedRect(margin, y, 40, 5, 1, 1, 'F');
                doc.setFontSize(7);
                doc.setTextColor(255, 255, 255);
                doc.text('FAQPage Schema OK', margin + 2, y + 3);
                doc.setTextColor(0, 0, 0);
                y += 8;
            }
        } else {
            doc.setFontSize(9);
            doc.setTextColor(200, 50, 50);
            doc.text('Aucune FAQ détectée - Recommandation : ajouter 3-5 questions/réponses', margin, y);
            doc.setTextColor(0, 0, 0);
            y += 7;
        }

        y += 5;

        // Métadonnées
        addSubTitle('Métadonnées');
        
        const metaItems = [
            { label: 'Title', value: auditData.metadata.hasTitle, detail: auditData.metadata.title },
            { label: 'Description', value: auditData.metadata.hasDescription, detail: auditData.metadata.description },
            { label: 'Open Graph', value: auditData.metadata.hasOG, detail: auditData.metadata.ogTitle }
        ];

        metaItems.forEach(item => {
            checkPageBreak(8);
            
            doc.setFontSize(9);
            doc.setFont('helvetica', 'bold');
            doc.text(item.label + ':', margin, y);
            
            if (item.value) {
                doc.setFillColor(40, 163, 42);
                doc.roundedRect(margin + 30, y - 3, 8, 5, 1, 1, 'F');
                doc.setFontSize(6);
                doc.setTextColor(255, 255, 255);
                doc.text('OK', margin + 31, y);
                doc.setTextColor(0, 0, 0);
                
                if (item.detail) {
                    doc.setFont('helvetica', 'normal');
                    doc.setFontSize(8);
                    const detail = item.detail.length > 60 ? item.detail.substring(0, 57) + '...' : item.detail;
                    doc.text(detail, margin + 42, y);
                }
            } else {
                doc.setFillColor(220, 53, 69);
                doc.roundedRect(margin + 30, y - 3, 18, 5, 1, 1, 'F');
                doc.setFontSize(6);
                doc.setTextColor(255, 255, 255);
                doc.text('MANQUANT', margin + 31, y);
                doc.setTextColor(0, 0, 0);
            }
            
            y += 8;
        });

        // ==============================================
        // PAGE WORDPRESS: PLUGINS RECOMMANDES (si WordPress)
        // ==============================================

        if (auditData.isWordPress) {
            doc.addPage();
            pageNum++;
            y = 20;
            addFooter();

            addSectionTitle('PLUGINS WORDPRESS RECOMMANDES', '');

            doc.setFontSize(10);
            doc.setTextColor(100, 100, 100);
            y = addText('Votre site utilise WordPress ! Optimisez votre GEO avec ces plugins gratuits :', margin, y, contentWidth);
            y += 8;
            doc.setTextColor(0, 0, 0);

            // GEO Blocks Suite
            createBox(margin, y, contentWidth, 55, [232, 244, 253]);
            
            doc.setFontSize(14);
            doc.setFont('helvetica', 'bold');
            doc.setTextColor(102, 126, 234);
            doc.text('GEO Blocks Suite', margin + 5, y + 10);
            
            doc.setFillColor(40, 167, 69);
            doc.roundedRect(margin + 75, y + 5, 18, 6, 1, 1, 'F');
            doc.setFontSize(7);
            doc.setTextColor(255, 255, 255);
            doc.text('GRATUIT', margin + 77, y + 9);
            
            doc.setTextColor(0, 0, 0);
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(9);
            
            let blockY = y + 18;
            blockY = addText('Blocs Gutenberg optimises pour le SEO et les moteurs d\'IA :', margin + 5, blockY, contentWidth - 15);
            blockY += 2;
            blockY = addText('- Blockquote GEO : Citations avec Schema.org (auteur, source, date)', margin + 10, blockY, contentWidth - 20);
            blockY = addText('- FAQ GEO : Questions/reponses avec FAQPage Schema', margin + 10, blockY, contentWidth - 20);
            blockY = addText('- Image GEO : Images avec metadonnees enrichies et alt optimise', margin + 10, blockY, contentWidth - 20);
            blockY = addText('- Video GEO / Audio GEO : Medias avec VideoObject/AudioObject Schema', margin + 10, blockY, contentWidth - 20);
            
            doc.setFontSize(8);
            doc.setTextColor(102, 126, 234);
            doc.text('wiki.ticoet.me/doku.php?id=geo_blocks_suite', margin + 5, y + 50);
            doc.setTextColor(0, 0, 0);
            
            y += 62;

            // GEO Authority Suite
            createBox(margin, y, contentWidth, 55, [255, 248, 220]);
            
            doc.setFontSize(14);
            doc.setFont('helvetica', 'bold');
            doc.setTextColor(180, 130, 0);
            doc.text('GEO Authority Suite', margin + 5, y + 10);
            
            doc.setFillColor(40, 167, 69);
            doc.roundedRect(margin + 85, y + 5, 18, 6, 1, 1, 'F');
            doc.setFontSize(7);
            doc.setTextColor(255, 255, 255);
            doc.text('GRATUIT', margin + 87, y + 9);
            
            doc.setTextColor(0, 0, 0);
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(9);
            
            let authY = y + 18;
            authY = addText('Suite complete pour l\'autorite et la visibilite IA :', margin + 5, authY, contentWidth - 15);
            authY += 2;
            authY = addText('- Audits GEO internes automatises sur toutes vos pages', margin + 10, authY, contentWidth - 20);
            authY = addText('- Creation automatique des entites Schema.org manquantes', margin + 10, authY, contentWidth - 20);
            authY = addText('- Generation du fichier llms.txt pour les LLM (ChatGPT, Claude, etc.)', margin + 10, authY, contentWidth - 20);
            authY = addText('- Tableau de bord centralise avec scores et recommandations', margin + 10, authY, contentWidth - 20);
            
            doc.setFontSize(8);
            doc.setTextColor(180, 130, 0);
            doc.text('wiki.ticoet.me/doku.php?id=entity-authority-signals', margin + 5, y + 50);
            doc.setTextColor(0, 0, 0);
            
            y += 62;

            // Call to action
            checkPageBreak(35);
            createBox(margin, y, contentWidth, 30, [212, 237, 218]);
            
            doc.setFontSize(11);
            doc.setFont('helvetica', 'bold');
            doc.setTextColor(40, 163, 42);
            doc.text('Passez a l\'action !', margin + 5, y + 10);
            
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(9);
            doc.setTextColor(0, 0, 0);
            doc.text('1. Telechargez les plugins sur wiki.ticoet.me', margin + 5, y + 18);
            doc.text('2. Installez-les dans WordPress (Extensions > Ajouter > Televerser)', margin + 5, y + 24);
            
            y += 38;
        }

        // ==============================================
        // PAGE FINALE: RESSOURCES
        // ==============================================

        doc.addPage();
        pageNum++;
        y = 20;
        addFooter();

        addSectionTitle('RESSOURCES & PROCHAINES ETAPES', '');

        addSubTitle('Outils de validation');
        doc.setFontSize(9);
        y = addText('• Google Rich Results Test : search.google.com/test/rich-results', margin, y, contentWidth);
        y = addText('• Schema.org Validator : validator.schema.org', margin, y, contentWidth);
        y = addText('• Outil d\'audit GEO Ticoët : audit.ticoet.me', margin, y, contentWidth);
        y += 5;

        addSubTitle('Documentation');
        y = addText('• Schema.org : schema.org/docs/documents.html', margin, y, contentWidth);
        y = addText('• Guide JSON-LD : json-ld.org', margin, y, contentWidth);
        y = addText('• Guide GEO complet : ticoet.fr/geo', margin, y, contentWidth);
        y += 10;

        addSubTitle('Besoin d\'aide ?');
        createBox(margin, y, contentWidth, 25, [232, 244, 253]);
        doc.setFontSize(10);
        doc.text('Pour une optimisation GEO professionnelle de votre site :', margin + 5, y + 8);
        doc.setFont('helvetica', 'bold');
        doc.setTextColor(102, 126, 234);
        doc.text('Contactez Ticoët : ticoet.fr', margin + 5, y + 18);
        doc.setTextColor(0, 0, 0);
        doc.setFont('helvetica', 'normal');

        // ==============================================
        // TÉLÉCHARGER
        // ==============================================

        const filename = `audit-geo-${auditData.url.replace(/[^a-z0-9]/gi, '-').substring(0, 30)}-${new Date().toISOString().split('T')[0]}.pdf`;
        doc.save(filename);

    } catch (error) {
        console.error('Erreur export PDF:', error);
        alert('Erreur lors de la génération du PDF: ' + error.message);
    }
}
