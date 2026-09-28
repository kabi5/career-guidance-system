"""Flask API for the AI/ML recommendation engine and career chatbot."""
from flask import Flask, request, jsonify
from flask_cors import CORS

from riasec import score_responses, top_three_code
from model import recommend
from programmes import PROGRAMMES
from chatbot import BOT

app = Flask(__name__)
CORS(app)


@app.route("/health", methods=["GET"])
def health():
    return jsonify({"status": "ok", "programmes": len(PROGRAMMES)})


@app.route("/score", methods=["POST"])
def score():
    data = request.get_json(force=True)
    scores = score_responses(data.get("responses", []))
    return jsonify({"scores": scores, "top_code": top_three_code(scores)})


@app.route("/recommend", methods=["POST"])
def recommend_endpoint():
    data = request.get_json(force=True)

    scores = data.get("riasec_scores")
    if not scores:
        scores = score_responses(data.get("responses", []))

    results = recommend(
        riasec_scores=scores,
        marks=data.get("marks", {}),
        subjects=data.get("subjects", []),
        aspirations=data.get("aspirations", ""),
        top_n=int(data.get("top_n", 5)),
    )
    return jsonify({
        "riasec_scores": scores,
        "top_code": top_three_code(scores),
        "recommendations": results,
    })


@app.route("/programmes", methods=["GET"])
def programmes():
    uni = (request.args.get("university") or "").lower()
    items = [p for p in PROGRAMMES if not uni or uni in p["university"].lower()]
    return jsonify({"count": len(items), "programmes": items})


@app.route("/chat", methods=["POST"])
def chat():
    data = request.get_json(force=True)
    return jsonify(BOT.reply(data.get("message", ""), data.get("context", {})))


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5001, debug=True)