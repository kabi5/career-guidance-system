"""Free, offline career chatbot: TF-IDF retrieval over careers, programmes and FAQ."""
import json
import os
import re

from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity

from model import CAREERS, EDUCATION, _infer_subjects
from programmes import PROGRAMMES, programmes_for_career

BASE_DIR = os.path.dirname(__file__)
FAQ_P = os.path.join(BASE_DIR, "data", "faq.json")

RIASEC_INFO = {
    "R": ("Realistic", "You like practical, hands-on work with tools, machines, animals or the outdoors."),
    "I": ("Investigative", "You like analysing, researching and solving complex problems."),
    "A": ("Artistic", "You like creative, expressive work such as design, writing or performance."),
    "S": ("Social", "You like helping, teaching, advising and caring for people."),
    "E": ("Enterprising", "You like leading, persuading, selling and running things."),
    "C": ("Conventional", "You like organised, detail-focused work with data, records and procedures."),
}

UNIVERSITY_ALIASES = {
    "nul": "National University of Lesotho",
    "national university": "National University of Lesotho",
    "botho": "Botho University Lesotho",
    "limkokwing": "Limkokwing University of Creative Technology (Lesotho)",
    "lerotholi": "Lerotholi Polytechnic",
    "polytechnic": "Lerotholi Polytechnic",
    "lce": "Lesotho College of Education",
    "college of education": "Lesotho College of Education",
    "agricultural college": "Lesotho Agricultural College",
}

GREETINGS = ("hi", "hello", "hey", "good morning", "good afternoon", "good evening")
THANKS = ("thanks", "thank you", "cheers")

FALLBACK = ("I'm not sure I have a good answer to that. I can help with career options, "
            "school subjects, qualifications, and programmes at Lesotho institutions. "
            "Try asking, for example: \"What can I study to become a nurse?\" or "
            "\"What does Botho University offer?\"")


def _load_faq():
    try:
        with open(FAQ_P, "r", encoding="utf-8") as f:
            return json.load(f)
    except FileNotFoundError:
        return []


def _programme_text(p):
    flag = "" if p.get("verified") else " (check the current prospectus to confirm details)"
    subj = ", ".join(p.get("required_subjects", [])) or "see prospectus"
    dur = f", {p['duration_years']} years" if p.get("duration_years") else ""
    return (f"{p['programme']} - {p['university']} ({p.get('level', '')}{dur}). "
            f"Subjects: {subj}. {p.get('entry_requirements', '')}{flag}")


def _career_answer(career):
    soc = career.get("soc_code", "")
    edu = EDUCATION.get("by_occupation", {}).get(soc, {})
    level = edu.get("most_common_label", "not specified")
    level = level.split(" - ")[0]  # shorten the long O*NET labels
    progs = programmes_for_career(career["title"], limit=3)

    lines = [f"{career['title']}: Holland code {career.get('riasec_code', '?')}.",
             f"Typical education (US data): {level}.",
             f"Useful subjects: {_infer_subjects(career)}."]
    if progs:
        lines.append("Where to study in Lesotho: "
                     + "; ".join(f"{p['programme']} at {p['university']}" for p in progs) + ".")
    else:
        lines.append("No matching local programme is listed yet; ask a career counsellor "
                     "about related fields.")
    return "\n".join(lines)


class CareerBot:
    def __init__(self):
        self.docs = []

        for item in _load_faq():
            self.docs.append({"kind": "faq", "title": item["question"],
                              "text": item["question"], "answer": item["answer"]})

        for p in PROGRAMMES:
            text = " ".join([p["programme"], p["university"], p.get("faculty", ""),
                             p.get("description", ""), " ".join(p.get("related_careers", []))])
            self.docs.append({"kind": "programme", "title": p["programme"], "text": text,
                              "answer": _programme_text(p), "university": p["university"]})

        for c in CAREERS:
            self.docs.append({"kind": "career", "title": c["title"],
                              "text": c["title"] + " " + c["title"],  # weight the title
                              "answer": None, "career": c})

        self.vectorizer = TfidfVectorizer(stop_words="english", ngram_range=(1, 2),
                                          sublinear_tf=True)
        self.matrix = self.vectorizer.fit_transform([d["text"] for d in self.docs])

    # ---- helpers ---------------------------------------------------------
    def _detect_university(self, msg):
        for alias, full in UNIVERSITY_ALIASES.items():
            if re.search(r"\b" + re.escape(alias) + r"\b", msg):
                return full
        return None

    def _answer_for(self, doc):
        if doc["kind"] == "career":
            return _career_answer(doc["career"])
        return doc["answer"]

    # ---- main entry point ------------------------------------------------
    def reply(self, message, context=None):
        context = context or {}
        msg = (message or "").strip().lower()
        if not msg:
            return {"reply": "Please type a question.", "sources": []}

        if msg in GREETINGS or any(msg.startswith(g + " ") for g in GREETINGS):
            return {"reply": "Hello! I'm the Career Compass assistant. Ask me about careers, "
                             "subjects, qualifications or programmes at Lesotho institutions.",
                    "sources": []}
        if any(t in msg for t in THANKS):
            return {"reply": "You're welcome! Ask me anything else about your future.",
                    "sources": []}

        # "my results / my code" questions
        code = context.get("riasec_code") or ""
        if code and re.search(r"\b(my|me)\b.*\b(result|code|profile|interest|type)s?\b", msg):
            parts = [f"Your Holland code is {code}."]
            for ch in code:
                if ch in RIASEC_INFO:
                    name, desc = RIASEC_INFO[ch]
                    parts.append(f"{ch} - {name}: {desc}")
            parts.append("See your dashboard for the careers matched to this profile.")
            return {"reply": "\n".join(parts), "sources": ["Your assessment"]}

        # "what does <university> offer" questions
        uni = self._detect_university(msg)
        if uni and re.search(r"offer|programme|program|course|study|faculty|qualification", msg):
            progs = [p for p in PROGRAMMES if p["university"] == uni]
            if progs:
                lines = [f"Programmes listed for {uni}:"]
                lines += ["- " + _programme_text(p) for p in progs]
                return {"reply": "\n".join(lines), "sources": [uni]}

        # Retrieval
        q = self.vectorizer.transform([msg])
        sims = cosine_similarity(q, self.matrix)[0]

        # Prefer programmes of a named university
        if uni:
            for i, d in enumerate(self.docs):
                if d.get("university") == uni:
                    sims[i] *= 1.5

        ranked = sims.argsort()[::-1][:3]
        best = ranked[0]
        if sims[best] < 0.12:
            return {"reply": FALLBACK, "sources": []}

        top_doc = self.docs[best]
        reply = self._answer_for(top_doc)
        sources = [top_doc["title"]]

        # add one related programme if the answer is about a career/FAQ
        for i in ranked[1:]:
            d = self.docs[i]
            if sims[i] >= 0.2 and d["kind"] == "programme" and top_doc["kind"] != "programme":
                reply += "\n\nRelated programme: " + d["answer"]
                sources.append(d["title"])
                break

        reply += "\n\n(This is guidance only. Please confirm details with a teacher or the institution.)"
        return {"reply": reply, "sources": sources}


BOT = CareerBot()