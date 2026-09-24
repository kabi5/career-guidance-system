"""Hybrid recommendation engine: RIASEC similarity + academic fit + demand + education filter."""
import json
import os
import numpy as np
from sklearn.metrics.pairwise import cosine_similarity

from riasec import DIMENSIONS, top_three_code

BASE_DIR   = os.path.dirname(__file__)
CAREERS_P  = os.path.join(BASE_DIR, "data", "processed", "careers.json")
EDU_P      = os.path.join(BASE_DIR, "data", "processed", "education.json")

RIASEC_INDEX = {d: i for i, d in enumerate(DIMENSIONS)}
DIM_SHORT    = {"Realistic": "R", "Investigative": "I", "Artistic": "A",
                "Social": "S", "Enterprising": "E", "Conventional": "C"}

# ---- Load once at import time --------------------------------------------
def _load():
    with open(CAREERS_P, "r", encoding="utf-8") as f:
        careers = json.load(f)
    try:
        with open(EDU_P, "r", encoding="utf-8") as f:
            education = json.load(f)
    except FileNotFoundError:
        education = {"categories": {}, "by_occupation": {}}
    return careers, education

CAREERS, EDUCATION = _load()


def _onehot_from_code(code, scores=None):
    """Prefer real RIASEC scores if available; else build from the 3-letter code."""
    if scores:
        vec = np.array([scores.get(d, 0) for d in DIMENSIONS], dtype=float)
        if vec.sum() > 0:
            return vec
    vec = np.zeros(len(DIMENSIONS))
    for ch in code:
        for name, short in DIM_SHORT.items():
            if short == ch:
                vec[RIASEC_INDEX[name]] = 1.0
    if vec.sum() == 0:
        vec[:] = 1.0 / len(DIMENSIONS)
    return vec


def _academic_fit(career, marks, subjects):
    """
    Heuristic academic fit. Since we don't have per-career subject requirements
    in the O*NET files, we infer them from the education level + RIASEC profile.
    """
    score = 0.4   # baseline

    # Maths matters a lot for Investigative / Conventional / Realistic occupations
    profile = career.get("riasec_code", "")
    maths   = float(marks.get("maths", 0) or 0)
    science = float(marks.get("science", 0) or 0)
    english = float(marks.get("english", 0) or 0)

    if any(ch in profile for ch in ("I", "C", "R")):
        score += (maths / 100.0) * 0.35
    if "I" in profile or "R" in profile:
        score += (science / 100.0) * 0.20
    if any(ch in profile for ch in ("S", "A", "E")):
        score += (english / 100.0) * 0.25

    return min(score, 1.0)


DEMAND_WEIGHT = {"high": 1.0, "medium": 0.75, "low": 0.5}


def recommend(riasec_scores, marks, subjects, aspirations="", top_n=5,
              education_filter=None, min_score=0.30):
    """
    riasec_scores: dict {"R":0-100, "I":0-100, ...}
    marks:         dict {"maths":70,"science":65,"english":72}
    subjects:      list of subject names
    aspirations:   free-text string
    top_n:         number of results
    education_filter: optional list of education categories (1-12) to include;
                      if None, all are kept. Example: [5,6,7,8] for Diploma/Bachelor.
    """
    learner_vec = np.array([riasec_scores.get(d, 0) for d in DIMENSIONS], dtype=float)
    if learner_vec.sum() == 0:
        learner_vec = np.ones(len(DIMENSIONS)) / len(DIMENSIONS)

    learner_vec = learner_vec.reshape(1, -1)
    learner_code = top_three_code(riasec_scores)
    aspiration_text = (aspirations or "").lower()

    results = []
    for career in CAREERS:
        soc = career.get("soc_code", "")

        # Education filter
        edu_entry = EDUCATION.get("by_occupation", {}).get(soc)
        if education_filter and edu_entry:
            top_cat = edu_entry.get("most_common_category")
            if top_cat not in education_filter:
                continue

        # Interest similarity
        career_vec = _onehot_from_code(career.get("riasec_code", ""),
                                       scores=career.get("riasec_scores")).reshape(1, -1)
        interest_sim = float(cosine_similarity(learner_vec, career_vec)[0][0])

        academic = _academic_fit(career, marks, subjects)
        demand   = DEMAND_WEIGHT.get(career.get("demand_level", "high"), 0.75)

        # Aspiration bonus: title keyword match
        bonus = 0.0
        title_words = career["title"].lower().split()
        if any(w in aspiration_text for w in title_words if len(w) > 3):
            bonus = 0.10

        final = (interest_sim * 0.55) + (academic * 0.30) + (demand * 0.15) + bonus
        final = max(0.0, min(final, 1.0))

        if final < min_score:
            continue

        explanation = _build_explanation(career, riasec_scores, learner_code, academic, demand)

        results.append({
            "title": career["title"],
            "soc_code": soc,
            "match_score": round(final * 100, 2),
            "holland_code": career.get("riasec_code", ""),
            "explanation": explanation,
            "recommended_subjects": _infer_subjects(career),
            "pathway": _pathway_for(career, edu_entry),
            "education_level": edu_entry.get("most_common_label") if edu_entry else "Not specified",
            "demand_level": career.get("demand_level", "medium"),
            "description": career.get("description", ""),
        })

    results.sort(key=lambda r: r["match_score"], reverse=True)
    return results[:top_n]


def _build_explanation(career, scores, learner_code, academic, demand):
    top = max(scores, key=scores.get)
    parts = [
        f"Your top RIASEC interest is {top} ({scores[top]:.0f}%).",
        f"Your interest profile ({learner_code}) aligns with the {career.get('riasec_code','?')} profile of {career['title']}.",
    ]
    if academic > 0.65:
        parts.append("Your marks and subject combination match well.")
    elif academic > 0.45:
        parts.append("Some subjects need strengthening to improve your readiness.")
    else:
        parts.append("Consider working on the key subjects for this field.")
    parts.append({
        "high": "This occupation is in high demand.",
        "medium": "This occupation has moderate demand.",
        "low": "This occupation has lower demand — explore related specialisations.",
    }.get(career.get("demand_level", "medium")))
    return " ".join(parts)


def _infer_subjects(career):
    """Best-effort subject recommendation from the RIASEC profile."""
    code = career.get("riasec_code", "")
    subs = []
    if "R" in code:
        subs += ["Mathematics", "Physical Sciences"]
    if "I" in code:
        subs += ["Mathematics", "Physical Sciences", "Biology"]
    if "C" in code:
        subs += ["Mathematics", "Accounting"]
    if "A" in code:
        subs += ["English", "Art"]
    if "S" in code:
        subs += ["English", "Life Orientation"]
    if "E" in code:
        subs += ["Business Studies", "Mathematics"]
    # Deduplicate, keep order
    seen, out = set(), []
    for s in subs:
        if s not in seen:
            seen.add(s)
            out.append(s)
    return ", ".join(out[:4])


def _pathway_for(career, edu_entry):
    if edu_entry:
        return edu_entry.get("most_common_label", "Post-secondary study")
    return "Post-secondary study"