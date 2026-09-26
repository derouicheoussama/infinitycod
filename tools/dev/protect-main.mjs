#!/usr/bin/env node
/**
 * Protection de branche main — dépôts InfinityCod (API GitHub).
 * Config solo dev : pushes directs conservés, force-push + suppression
 * interdits pour TOUS (admin inclus), aucune exigence de PR/status check
 * (sinon le pipeline de release par push direct serait bloqué).
 *
 * Usage : node protect-main-tmp.mjs <owner/repo> [<owner/repo> …]
 */
import { execSync } from 'node:child_process';

const cred = execSync('printf "protocol=https\\nhost=github.com\\n\\n" | git credential fill', { encoding: 'utf8' });
let TOKEN = '';
for (const line of cred.split('\n')) {
	if (line.trim().startsWith('password=')) TOKEN = line.trim().slice(9);
}
if (!TOKEN) { console.error('token GitHub introuvable via git credential'); process.exit(1); }

// Configuration : interdire force-push et suppression, garder les pushes directs.
const PROTECTION = {
	required_status_checks: null,
	enforce_admins: true,          // les règles s'appliquent aussi à l'admin (force-push interdit même pour lui)
	required_pull_request_reviews: null, // pas d'exigence de PR : flux solo, pushes directs
	restrictions: null,            // (champ org-only, null = aucune limite d'utilisateurs)
	allow_force_pushes: false,
	allow_deletions: false,
	required_conversation_resolution: false,
	lock_branch: false,
};

const repos = process.argv.slice(2);
for (const repo of repos) {
	try {
		const res = await fetch(`https://api.github.com/repos/${repo}/branches/main/protection`, {
			method: 'PUT',
			headers: {
				'Authorization': `token ${TOKEN}`,
				'User-Agent': 'icod',
				'Accept': 'application/vnd.github+json',
				'Content-Type': 'application/json',
			},
			body: JSON.stringify(PROTECTION),
		});
		const text = await res.text();
		if (res.status === 200) {
			console.log(`✓ ${repo} — main protégée (force-push et suppression interdits, pushes directs OK)`);
		} else {
			let msg = text;
			try { msg = JSON.parse(text).message || text; } catch { }
			console.log(`✗ ${repo} — HTTP ${res.status} : ${msg.slice(0, 200)}`);
		}
	} catch (e) {
		console.log(`✗ ${repo} — erreur : ${e.message}`);
	}
}
