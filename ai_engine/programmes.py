"""Local university / college programme data and career matching."""
import json
import os
import re

BASE_DIR = os.path.dirname(__file__)
PROGRAMMES_P = os.path.join(BASE_DIR, "data", "programmes.json")


def _load():
    try:
        with open(PROGRAMMES_P, "r", encoding="utf-8") as f:
            return json.load(f)
    except FileNotFoundError:
        return []


PROGRAMMES = _load()


def canon(text):
    """Normalise names so 'Physical Sciences' == 'physical science'."""
    text = re.sub(r"[^a-z0-9 ]", " ", (text or "").lower())
    words = [re.sub(r"s$", "", w) for w in text.split()]
    return " ".join(words)


def programmes_for_career(career_title, learner_subjects=None, limit=4):
    """
    Return local programmes related to a career, best subject fit first.
    Each item includes which required subjects the learner has / lacks.
    """
    title = canon(career_title)
    learner = {canon(s) for s in (learner_subjects or [])}
    out = []

    for p in PROGRAMMES:
        if not any(canon(k) in title for k in p.get("related_careers", [])):
            continue

        required = p.get("required_subjects", [])
        met = [s for s in required if canon(s) in learner]
        missing = [s for s in required if canon(s) not in learner]
        fit = (len(met) / len(required)) if required else 1.0

        out.append({
            "id": p["id"],
            "university": p["university"],
            "programme": p["programme"],
            "level": p.get("level", ""),
            "duration_years": p.get("duration_years"),
            "required_subjects": required,
            "missing_subjects": missing,
            "subject_fit": round(fit, 2),
            "entry_requirements": p.get("entry_requirements", ""),
            "verified": bool(p.get("verified", False)),
        })

    out.sort(key=lambda x: x["subject_fit"], reverse=True)
    return out[:limit]


def local_availability_score(programmes):
    """0 if no local programme; otherwise 0.5-1.0 depending on subject fit."""
    if not programmes:
        return 0.0
    best = max(p["subject_fit"] for p in programmes)
    return 0.5 + 0.5 * best